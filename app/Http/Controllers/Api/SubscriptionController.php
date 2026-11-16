<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Support\CashfreeClient;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function plans()
    {
        return response()->json(
            Plan::ensureDefaults()->map(fn (Plan $plan) => $plan->toApiArray())->values()
        );
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $amount = (float) $plan->price;
        $orderId = CashfreeClient::newOrderId('PLAN'.$plan->id.'U'.$request->user()->id);

        try {
            $order = CashfreeClient::createOrder(
                $amount,
                $orderId,
                $request->user(),
                (string) ($plan->name ?: 'AMOSHWZ Membership')
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
            'gateway' => 'cashfree',
        ]);

        return response()->json(CashfreeClient::checkoutPayload(
            $order,
            $amount,
            (string) ($plan->name ?: 'AMOSHWZ Membership'),
            ['plan_id' => $plan->id]
        ));
    }

    public function verify(Request $request)
    {
        $request->validate([
            'order_id' => 'required_without:cashfree_order_id|string|nullable',
            'cashfree_order_id' => 'required_without:order_id|string|nullable',
        ]);

        $orderId = (string) ($request->input('order_id') ?: $request->input('cashfree_order_id'));

        try {
            $ok = CashfreeClient::orderIsPaid($orderId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if (! $ok) {
            return response()->json(['message' => 'Payment verification failed'], 422);
        }

        $payment = Payment::where('transaction_id', $orderId)
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
