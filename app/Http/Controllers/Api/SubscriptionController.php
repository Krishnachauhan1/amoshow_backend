<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\Request;
use Razorpay\Api\Api;

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

        $api   = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
        $order = $api->order->create([
            'amount'   => $plan->price * 100,
            'currency' => 'INR',
            'receipt'  => 'rcpt_' . uniqid(),
        ]);

        Payment::create([
            'user_id'        => $request->user()->id,
            'plan_id'        => $plan->id,
            'transaction_id' => $order->id,
            'amount'         => $plan->price,
            'status'         => 'pending',
        ]);

        return response()->json([
            'order_id' => $order->id,
            'amount'   => $plan->price,
            'currency' => 'INR',
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature'  => 'required',
        ]);

        $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));

        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Payment verification failed'], 422);
        }

        $payment = Payment::where('transaction_id', $request->razorpay_order_id)->firstOrFail();
        $payment->update(['status' => 'success']);

        $plan = Plan::find($payment->plan_id);

        $request->user()->subscriptions()->create([
            'plan_id'   => $plan->id,
            'status'    => 'active',
            'starts_at' => now(),
            'ends_at'   => now()->addDays($plan->duration_days),
        ]);

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