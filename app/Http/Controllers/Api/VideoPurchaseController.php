<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoPurchase;
use App\Support\RazorpayClient;
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
        $razorpayAmount = (int) round($price * 100);

        try {
            $order = RazorpayClient::createOrder(
                $razorpayAmount,
                'video_'.$video->id.'_'.$request->user()->id.'_'.uniqid()
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
            'gateway' => 'razorpay',
        ]);

        return response()->json(RazorpayClient::checkoutPayload(
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
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        if ($video->platform !== 'youtube') {
            return response()->json(['message' => 'Not found'], 404);
        }

        try {
            $ok = RazorpayClient::verifySignature(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if (! $ok) {
            return response()->json(['message' => 'Payment verification failed'], 422);
        }

        $purchase = VideoPurchase::where('transaction_id', $request->razorpay_order_id)
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
