<?php

namespace App\Support;

use Illuminate\Support\Str;

class VideoThumbnail
{
    /** @return list<string> */
    public static function ffmpegCandidates(): array
    {
        $candidates = [
            'ffmpeg',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
        ];

        $home = getenv('HOME') ?: '';
        if ($home !== '') {
            $candidates[] = $home . '/bin/ffmpeg';
        }

        $found = trim((string) (SafeShell::exec('command -v ffmpeg 2>/dev/null') ?? ''));
        if ($found !== '') {
            array_unshift($candidates, $found);
        }

        return array_values(array_unique($candidates));
    }

    public static function hasFfmpeg(): bool
    {
        return self::resolveFfmpegBinary() !== null;
    }

    public static function resolveFfmpegBinary(): ?string
    {
        foreach (self::ffmpegCandidates() as $binary) {
            if ($binary === 'ffmpeg' || str_starts_with($binary, '/')) {
                $check = SafeShell::exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null');
                if ($check !== null && trim($check) !== '') {
                    return trim($check);
                }
            }
            if (is_file($binary) && is_executable($binary)) {
                return $binary;
            }
        }

        return null;
    }

    /**
     * Extract a JPEG frame from a video (requires ffmpeg on server).
     */
    public static function generateFromVideo(string $videoFullPath): ?string
    {
        if (!is_file($videoFullPath) || !is_readable($videoFullPath)) {
            return null;
        }

        $ffmpeg = self::resolveFfmpegBinary();

        if ($ffmpeg === null) {
            return null;
        }

        $thumbRelative = 'thumbnails/' . Str::uuid() . '.jpg';
        $thumbFull     = storage_path('app/public/' . $thumbRelative);

        if (!is_dir(dirname($thumbFull))) {
            mkdir(dirname($thumbFull), 0755, true);
        }

        $cmd = sprintf(
            '%s -y -i %s -ss 00:00:01 -vframes 1 -q:v 2 %s 2>/dev/null',
            escapeshellarg($ffmpeg),
            escapeshellarg($videoFullPath),
            escapeshellarg($thumbFull)
        );

        $exitCode = SafeShell::run($cmd);

        if ($exitCode !== 0 || !is_file($thumbFull) || filesize($thumbFull) < 100) {
            if (is_file($thumbFull)) {
                @unlink($thumbFull);
            }

            return null;
        }

        return $thumbRelative;
    }
}
