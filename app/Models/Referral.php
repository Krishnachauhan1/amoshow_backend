<?php

namespace App\Models;

use App\Support\YoutubeFormatter;
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

    public static function peopleFor(User $user): array
    {
        static::ensureTable();

        $rows = static::query()
            ->where('referrer_id', $user->id)
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $people = User::query()
            ->whereIn('id', $rows->pluck('referred_id'))
            ->get()
            ->keyBy('id');

        $grossByUser = static::grossPaiseByUser($rows->pluck('referred_id'));

        return $rows->map(function (self $row) use ($people, $grossByUser) {
            $person = $people->get($row->referred_id);
            $grossPaise = (int) ($grossByUser[(int) $row->referred_id] ?? 0);
            $bonusPaise = static::bonusFromGross($grossPaise);

            return [
                'id' => (int) $row->referred_id,
                'name' => $person?->name ?: 'AMOSHWZ user',
                'email' => $person?->email,
                'avatar_url' => YoutubeFormatter::storageUrl($person?->avatar),
                'joined_at' => optional($row->created_at)?->toIso8601String(),
                'earnings' => YoutubeFormatter::earnings($bonusPaise),
                'earnings_paise' => $bonusPaise,
            ];
        })->values()->all();
    }

    public static function rates(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $map = AppSetting::asMap();

        return $cached = [
            'platform' => (float) ($map['platform_share_percent'] ?? 40),
            'referral' => (float) ($map['referral_percent_of_platform'] ?? 5),
        ];
    }

    public static function bonusFromGross(int $grossPaise): int
    {
        if ($grossPaise <= 0) {
            return 0;
        }

        $rates = static::rates();

        return (int) round(
            $grossPaise * ($rates['platform'] / 100) * ($rates['referral'] / 100)
        );
    }

    public static function grossPaiseByUser(iterable $userIds): array
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty() || ! Schema::hasTable('videos') || ! Schema::hasTable('channels')) {
            return [];
        }

        return Video::query()
            ->join('channels', 'channels.id', '=', 'videos.channel_id')
            ->whereIn('channels.user_id', $ids)
            ->groupBy('channels.user_id')
            ->selectRaw('channels.user_id, COALESCE(SUM(videos.earnings_paise), 0) as gross')
            ->pluck('gross', 'user_id')
            ->map(fn ($gross) => (int) $gross)
            ->all();
    }

    public static function earningsSummaryFor(User $user): array
    {
        static::ensureTable();

        $rates = static::rates();
        $referredIds = static::query()->where('referrer_id', $user->id)->pluck('referred_id');
        $grossByUser = static::grossPaiseByUser($referredIds);
        $grossPaise = (int) array_sum($grossByUser);
        $bonusPaise = static::bonusFromGross($grossPaise);

        return [
            'gross_paise' => $grossPaise,
            'bonus_paise' => $bonusPaise,
            'platform_percent' => $rates['platform'],
            'referral_percent' => $rates['referral'],
            'earnings' => YoutubeFormatter::earnings($bonusPaise),
        ];
    }
}
