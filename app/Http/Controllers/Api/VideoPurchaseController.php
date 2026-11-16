<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoPurchase;
use App\Support\CashfreeClient;
use Illuminate\Http\Request;
use RuntimeException;

class VideoPurchaseController extends Controller
{
    public function initiate(Request $request, Video $video)
    {
        if ($video->platform !== 'youtube') {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (! $video->isPaidContent()) {
            return response()->json(['message' => 'This video is free to watch'], 422);
        }

        if ($video->channel?->user_id === $request->user()->id) {
            return response()->json(['message' => 'You own this video'], 422);
        }

        if ($video->userHasPurchased($request->user())) {
            return response()->json([
                'message' => 'Already purchased',
                'has_purchased' => true,
            ]);
        }

        $price = (float) $video->price;
        $orderId = CashfreeClient::newOrderId('VID'.$video->id.'U'.$request->user()->id);

        try {
            $order = CashfreeClient::createOrder(
                $price,
                $orderId,
                $request->user(),
                (string) ($video->title ?: 'Paid video')
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        VideoPurchase::create([
            'user_id' => $request->user()->id,
            'video_id' => $video->id,
            'amount' => $price,
            'transaction_id' => $order['id'],
            'status' => 'pending',
            'gateway' => 'cashfree',
        ]);

        return response()->json(CashfreeClient::checkoutPayload(
            $order,
            $price,
            (string) ($video->title ?: 'Paid video'),
            [
                'price' => $price,
                'video_id' => $video->id,
                'video_title' => $video->title,
            ]
        ));
    }

    public function verify(Request $request, Video $video)
    {
        $request->validate([
            'order_id' => 'required_without:cashfree_order_id|string|nullable',
            'cashfree_order_id' => 'required_without:order_id|string|nullable',
        ]);

        if ($video->platform !== 'youtube') {
            return response()->json(['message' => 'Not found'], 404);
        }

        $orderId = (string) ($request->input('order_id') ?: $request->input('cashfree_order_id'));

        try {
            $ok = CashfreeClient::orderIsPaid($orderId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if (! $ok) {
            return response()->json(['message' => 'Payment verification failed'], 422);
        }

        $purchase = VideoPurchase::where('transaction_id', $orderId)
            ->where('video_id', $video->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($purchase->isSuccessful()) {
            return response()->json([
                'message' => 'Already purchased',
                'has_purchased' => true,
            ]);
        }

        $purchase->update(['status' => 'success']);

        $video->increment('earnings_paise', (int) round((float) $purchase->amount * 100));

        return response()->json([
            'message' => 'Payment successful. You can now watch this video.',
            'has_purchased' => true,
        ]);
    }
}
