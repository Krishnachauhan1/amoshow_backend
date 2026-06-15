<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class YoutubeFormatter
{
    public static function duration(?int $seconds): string
    {
        if (!$seconds || $seconds <= 0) {
            return '0:00';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }

        return sprintf('%d:%02d', $m, $s);
    }

    public static function views(int $count): string
    {
        if ($count >= 1_000_000) {
            return round($count / 1_000_000, 1) . 'M views';
        }
        if ($count >= 1_000) {
            return round($count / 1_000, 1) . 'K views';
        }

        return $count . ' views';
    }

    public static function subscribers(int $count): string
    {
        if ($count >= 1_000_000) {
            return round($count / 1_000_000, 1) . 'M';
        }
        if ($count >= 1_000) {
            return round($count / 1_000, 1) . 'K';
        }

        return (string) $count;
    }

    public static function timeAgo(?Carbon $date): string
    {
        if (!$date) {
            return 'just now';
        }

        return $date->diffForHumans(short: true);
    }

    public static function earnings(int $paise): string
    {
        $rupees = (int) round($paise / 100);

        return '₹' . number_format($rupees);
    }

    /**
     * Public media URL. Set PUBLIC_STORAGE_PREFIX in .env:
     * - "storage" → https://domain.com/storage/... (docroot = Laravel /public)
     * - "public/storage" → https://domain.com/public/storage/... (docroot = project root)
     */
    public static function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return self::normalizePublicUrl($path);
        }

        $relative = ltrim($path, '/');
        $prefix   = self::publicStoragePrefix();

        if ($prefix === 'storage') {
            return self::fixMediaHost(Storage::disk('public')->url($relative));
        }

        return self::mediaBaseUrl() . '/' . $prefix . '/' . $relative;
    }

    private static function publicStoragePrefix(): string
    {
        return trim((string) env('PUBLIC_STORAGE_PREFIX', 'storage'), '/');
    }

    private static function normalizePublicUrl(string $url): string
    {
        $url = self::fixMediaHost($url);

        if (str_contains($url, '/public/storage/')) {
            $url = str_replace('/public/storage/', '/storage/', $url);
        }

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            $path  = parse_url($url, PHP_URL_PATH) ?? '';
            $query = parse_url($url, PHP_URL_QUERY);
            $url   = self::mediaBaseUrl() . $path;
            if ($query) {
                $url .= '?' . $query;
            }
        }

        return $url;
    }

    /** API + media files live on amoshowapi.* (not amoshow.* frontend domain). */
    private static function mediaBaseUrl(): string
    {
        return rtrim(self::fixMediaHost(
            (string) config('app.public_storage_url', config('app.url'))
        ), '/');
    }

    private static function fixMediaHost(string $url): string
    {
        return (string) preg_replace(
            '#https?://amoshow\.nextlogicsolution\.id#i',
            'https://amoshowapi.nextlogicsolution.id',
            $url
        );
    }
}
