<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Support\RazorpayClient;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function plans()
    {
        return response()->json(Plan::all());
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $amountPaise = (int) round(((float) $plan->price) * 100);

        try {
            $order = RazorpayClient::createOrder(
                $amountPaise,
                'plan_'.$plan->id.'_'.$request->user()->id.'_'.uniqid()
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        Payment::create([
            'user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'transaction_id' => $order['id'],
            'amount' => $plan->price,
            'status' => 'pending',
            'gateway' => 'razorpay',
        ]);

        return response()->json(RazorpayClient::checkoutPayload(
            $order,
            (float) $plan->price,
            (string) ($plan->name ?: 'AMOSHWZ Membership'),
            ['plan_id' => $plan->id]
        ));
    }

    public function verify(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

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

        $payment = Payment::where('transaction_id', $request->razorpay_order_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $plan = Plan::findOrFail($payment->plan_id);

        if ($payment->status !== 'success') {
            $payment->update(['status' => 'success']);
        }

        $alreadyActive = $request->user()
            ->subscriptions()
            ->where('plan_id', $plan->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();

        if (! $alreadyActive) {
            $duration = max(1, (int) ($plan->duration_days ?: 30));
            $request->user()->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays($duration),
            ]);
        }

        return response()->json(['message' => 'Subscription activated successfully']);
    }

    public function current(Request $request)
    {
        $subscription = $request->user()
            ->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest()
            ->first();

        return response()->json($subscription);
    }
}
