<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayClient
{
    public static function keyId(): string
    {
        return trim((string) config('services.razorpay.key'));
    }

    public static function secret(): string
    {
        return trim((string) config('services.razorpay.secret'));
    }

    public static function isConfigured(): bool
    {
        return self::keyId() !== '' && self::secret() !== '';
    }

    public static function assertConfigured(): void
    {
        if (! self::isConfigured()) {
            throw new RuntimeException(
                'Razorpay is not configured. Set RAZORPAY_KEY and RAZORPAY_SECRET in .env, then run php artisan config:clear.'
            );
        }
    }

    /**
     * @return array{id: string, amount: int, currency: string}
     */
    public static function createOrder(int $amountPaise, string $receipt, string $currency = 'INR'): array
    {
        self::assertConfigured();

        if ($amountPaise < 100) {
            throw new RuntimeException('Amount must be at least ₹1.');
        }

        try {
            $response = Http::withBasicAuth(self::keyId(), self::secret())
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountPaise,
                    'currency' => $currency,
                    'receipt' => substr($receipt, 0, 40),
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to reach Razorpay. '.$e->getMessage());
        }

        if (! $response->successful()) {
            $message = $response->json('error.description')
                ?? $response->json('error.reason')
                ?? 'Unable to create Razorpay order';

            throw new RuntimeException((string) $message);
        }

        $id = (string) $response->json('id');
        if ($id === '') {
            throw new RuntimeException('Razorpay did not return an order id.');
        }

        return [
            'id' => $id,
            'amount' => (int) $response->json('amount', $amountPaise),
            'currency' => (string) $response->json('currency', $currency),
        ];
    }

    public static function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        self::assertConfigured();

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, self::secret());

        return hash_equals($expected, $signature);
    }

    public static function checkoutPayload(
        array $order,
        float $amountRupees,
        string $description,
        array $extra = []
    ): array {
        return array_merge([
            'key_id' => self::keyId(),
            'order_id' => $order['id'],
            'amount' => $amountRupees,
            'amount_paise' => (int) $order['amount'],
            'currency' => $order['currency'] ?? 'INR',
            'name' => 'AMOSHWZ',
            'description' => $description,
        ], $extra);
    }
}
