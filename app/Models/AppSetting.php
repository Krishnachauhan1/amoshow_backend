<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'label'];

    public static function defaults(): array
    {
        return [
            'creator_share_percent' => ['60', 'integer', 'Creator share %'],
            'platform_share_percent' => ['40', 'integer', 'Platform share %'],
            'referral_percent_of_platform' => ['5', 'number', 'Referral % of platform cut'],
            'min_payout_rupees' => ['500', 'number', 'Minimum payout (₹)'],
            'payout_hold_days' => ['7', 'integer', 'Payout hold days'],
            'install_voucher_code' => ['AMOSHWZ50', 'string', 'Install voucher code'],
            'install_voucher_amount' => ['50', 'number', 'Install voucher amount (₹)'],
            'install_voucher_valid_days' => ['30', 'integer', 'Install voucher valid days'],
            'channel_offer_credit' => ['100', 'number', 'Channel offer credit (₹)'],
            'channel_offer_expiry_days' => ['14', 'integer', 'Channel offer expiry days'],
        ];
    }

    public static function ensureReady(): void
    {
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->string('label')->nullable();
                $table->timestamps();
            });
        }

        foreach (self::defaults() as $key => [$value, $type, $label]) {
            self::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'label' => $label]
            );
        }
    }

    public static function asMap(): array
    {
        self::ensureReady();

        $out = [];
        foreach (self::query()->get() as $row) {
            $out[$row->key] = $row->typedValue();
        }

        return $out;
    }

    public static function putMany(array $values): array
    {
        self::ensureReady();

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::defaults())) {
                continue;
            }

            $meta = self::defaults()[$key];
            self::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                    'type' => $meta[1],
                    'label' => $meta[2],
                ]
            );
        }

        return self::asMap();
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'number' => str_contains((string) $this->value, '.')
                ? (float) $this->value
                : (int) $this->value,
            'boolean' => in_array($this->value, ['1', 'true', 'yes'], true),
            default => $this->value,
        };
    }
}
