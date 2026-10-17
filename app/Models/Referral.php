<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class Referral extends Model
{
    protected $fillable = ['referrer_id', 'referred_id', 'code'];

    public static function ensureTable(): void
    {
        if (Schema::hasTable('referrals')) {
            return;
        }

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_id')->unique();
            $table->string('code', 40);
            $table->timestamps();
            $table->index('referrer_id');
        });
    }

    public static function codeFor(User $user): string
    {
        return 'AM'.$user->id;
    }

    public static function publicBaseUrl(?Request $request = null): string
    {
        $host = $request?->getSchemeAndHttpHost();
        if (is_string($host) && $host !== '' && ! str_contains($host, 'localhost')) {
            return rtrim($host, '/');
        }

        $url = rtrim((string) config('app.url'), '/');
        if ($url === '' || str_contains($url, 'localhost')) {
            return 'http://amoshwz.com';
        }

        return $url;
    }

    public static function linkFor(User $user, ?Request $request = null): string
    {
        return static::publicBaseUrl($request).'/ref/'.static::codeFor($user);
    }

    public static function normalizeCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if (preg_match('#/ref/([A-Z0-9]+)#', $code, $matches)) {
            return $matches[1];
        }

        return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    }

    public static function findReferrer(?string $code): ?User
    {
        $code = static::normalizeCode($code);
        if ($code === '') {
            return null;
        }

        if (preg_match('/^AM(\d+)$/', $code, $matches)) {
            return User::query()->find((int) $matches[1]);
        }

        if (ctype_digit($code)) {
            return User::query()->find((int) $code);
        }

        return null;
    }

    public static function attach(User $newUser, ?string $code): void
    {
        static::ensureTable();

        $referrer = static::findReferrer($code);
        if (! $referrer || $referrer->id === $newUser->id) {
            return;
        }

        if (static::query()->where('referred_id', $newUser->id)->exists()) {
            return;
        }

        static::query()->create([
            'referrer_id' => $referrer->id,
            'referred_id' => $newUser->id,
            'code' => static::codeFor($referrer),
        ]);
    }

    public static function countFor(User $user): int
    {
        static::ensureTable();

        return static::query()->where('referrer_id', $user->id)->count();
    }
}
