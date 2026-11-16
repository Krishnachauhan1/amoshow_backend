<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CashfreeClient
{
    public static function appId(): string
    {
        return trim((string) config('services.cashfree.app_id'));
    }

    public static function secret(): string
    {
        return trim((string) config('services.cashfree.secret'));
    }

    public static function environment(): string
    {
        $configured = strtolower(trim((string) config('services.cashfree.env', 'sandbox')));
        if (str_starts_with(strtoupper(self::appId()), 'TEST')) {
            return 'sandbox';
        }

        return $configured === 'production' ? 'production' : 'sandbox';
    }

    public static function isSandbox(): bool
    {
        return self::environment() !== 'production';
    }

    public static function isConfigured(): bool
    {
        return self::appId() !== '' && self::secret() !== '';
    }

    public static function assertConfigured(): void
    {
        if (! self::isConfigured()) {
            throw new RuntimeException(
                'Cashfree is not configured. Set CASHFREE_APP_ID and CASHFREE_SECRET_KEY in .env, then run php artisan config:clear.'
            );
        }
    }

    public static function baseUrl(): string
    {
        return self::isSandbox()
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    public static function newOrderId(string $prefix): string
    {
        $raw = strtoupper($prefix.'_'.bin2hex(random_bytes(8)));
        $clean = preg_replace('/[^A-Z0-9_]/', '', $raw) ?? $raw;

        return substr($clean, 0, 50);
    }

    /**
     * @return array{id: string, payment_session_id: string, amount: float, currency: string, environment: string}
     */
    public static function createOrder(
        float $amountRupees,
        string $orderId,
        User $user,
        string $note = ''
    ): array {
        self::assertConfigured();

        if ($amountRupees < 1) {
            throw new RuntimeException('Amount must be at least ₹1.');
        }

        $payload = [
            'order_id' => $orderId,
            'order_amount' => round($amountRupees, 2),
            'order_currency' => 'INR',
            'order_note' => substr($note !== '' ? $note : 'AMOSHWZ', 0, 100),
            'customer_details' => self::customerDetails($user),
            'order_meta' => [
                'return_url' => rtrim((string) config('app.url'), '/').'/?order_id={order_id}',
            ],
        ];

        try {
            $response = self::http()->post(self::baseUrl().'/orders', $payload);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to reach Cashfree. '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException(self::errorMessage($response, 'Unable to create Cashfree order'));
        }

        $id = (string) $response->json('order_id');
        $sessionId = (string) $response->json('payment_session_id');
        if ($id === '' || $sessionId === '') {
            throw new RuntimeException('Cashfree did not return a payment session.');
        }

        return [
            'id' => $id,
            'payment_session_id' => $sessionId,
            'amount' => (float) $response->json('order_amount', $amountRupees),
            'currency' => (string) $response->json('order_currency', 'INR'),
            'environment' => self::environment(),
        ];
    }

    public static function orderIsPaid(string $orderId): bool
    {
        self::assertConfigured();

        $orderId = trim($orderId);
        if ($orderId === '') {
            return false;
        }

        try {
            $response = self::http()->get(self::baseUrl().'/orders/'.$orderId);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to reach Cashfree. '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException(self::errorMessage($response, 'Unable to verify Cashfree payment'));
        }

        return strtoupper((string) $response->json('order_status')) === 'PAID';
    }

    public static function checkoutPayload(
        array $order,
        float $amountRupees,
        string $description,
        array $extra = []
    ): array {
        return array_merge([
            'gateway' => 'cashfree',
            'order_id' => $order['id'],
            'payment_session_id' => $order['payment_session_id'],
            'environment' => $order['environment'] ?? self::environment(),
            'amount' => $amountRupees,
            'amount_paise' => (int) round($amountRupees * 100),
            'currency' => $order['currency'] ?? 'INR',
            'name' => 'AMOSHWZ',
            'description' => $description,
        ], $extra);
    }

    private static function customerDetails(User $user): array
    {
        $phone = preg_replace('/\D+/', '', (string) ($user->phone ?? $user->mobile ?? '')) ?? '';
        if (strlen($phone) < 10) {
            $phone = '9876543210';
        } else {
            $phone = substr($phone, -10);
        }

        $email = trim((string) $user->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'user'.$user->id.'@amoshwz.com';
        }

        return [
            'customer_id' => 'user_'.$user->id,
            'customer_name' => substr((string) ($user->name ?: 'AMOSHWZ user'), 0, 100),
            'customer_email' => $email,
            'customer_phone' => $phone,
        ];
    }

    private static function http()
    {
        return Http::withHeaders([
            'x-client-id' => self::appId(),
            'x-client-secret' => self::secret(),
            'x-api-version' => '2025-01-01',
            'Accept' => 'application/json',
        ])->asJson()->acceptJson()->timeout(20);
    }

    private static function errorMessage($response, string $fallback): string
    {
        $message = $response->json('message')
            ?? $response->json('message.code')
            ?? data_get($response->json(), 'message')
            ?? $fallback;

        if (is_array($message)) {
            $message = $message['message'] ?? $fallback;
        }

        return (string) $message;
    }
}
