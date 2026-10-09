<?php

namespace App\Support;

class VideoProbe
{
    /** @var list<string> */
    private const SKIP_BOXES = ['ftyp', 'free', 'skip', 'mdat', 'wide', 'uuid', 'meta'];

    /** @var list<string> */
    private const CONTAINER_BOXES = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'edts'];

    public static function durationSeconds(string $path): ?int
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $duration = self::ffprobeDuration($path);

        if ($duration !== null) {
            return $duration;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($ext, ['mp4', 'm4v', 'mov', 'qt'], true)) {
            return null;
        }

        $duration = self::mp4DurationFromStart($path);

        if ($duration !== null) {
            return $duration;
        }

        return self::mp4DurationFromMoovAtEnd($path);
    }

    private static function ffprobeDuration(string $path): ?int
    {
        $ffprobe = trim((string) (SafeShell::exec('command -v ffprobe 2>/dev/null') ?? ''));

        if ($ffprobe === '') {
            return null;
        }

        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($ffprobe),
            escapeshellarg($path)
        );

        $output = SafeShell::exec($cmd);

        if ($output === null || trim($output) === '') {
            return null;
        }

        $seconds = (float) trim($output);

        return $seconds > 0 ? (int) round($seconds) : null;
    }

    private static function mp4DurationFromStart(string $path): ?int
    {
        $handle = @fopen($path, 'rb');

        if (!$handle) {
            return null;
        }

        try {
            return self::findMvhdDuration($handle, 0, (int) filesize($path));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Phone / camera MP4 files usually place the moov atom at the end of the file.
     */
    private static function mp4DurationFromMoovAtEnd(string $path): ?int
    {
        $fileSize = (int) filesize($path);

        if ($fileSize < 32) {
            return null;
        }

        $scanSize = (int) min($fileSize, 8 * 1024 * 1024);
        $handle   = @fopen($path, 'rb');

        if (!$handle) {
            return null;
        }

        fseek($handle, $fileSize - $scanSize);
        $buffer = fread($handle, $scanSize);
        fclose($handle);

        if ($buffer === false || $buffer === '') {
            return null;
        }

        $bufferStart = $fileSize - strlen($buffer);
        $offset      = 0;

        while (($pos = strpos($buffer, 'moov', $offset)) !== false) {
            if ($pos >= 4) {
                $moovStart = $bufferStart + $pos - 4;
                $duration  = self::readMoovBoxDuration($path, $moovStart, $fileSize);

                if ($duration !== null) {
                    return $duration;
                }
            }

            $offset = $pos + 4;
        }

        return null;
    }

    private static function readMoovBoxDuration(string $path, int $moovStart, int $fileSize): ?int
    {
        $handle = @fopen($path, 'rb');

        if (!$handle) {
            return null;
        }

        try {
            if ($moovStart < 0 || $moovStart + 8 > $fileSize) {
                return null;
            }

            fseek($handle, $moovStart);
            $parsed = self::readBoxHeader($handle, $moovStart, $fileSize);

            if ($parsed === null || $parsed['type'] !== 'moov') {
                return null;
            }

            return self::findMvhdDuration($handle, $parsed['content_start'], $parsed['content_end']);
        } finally {
            fclose($handle);
        }
    }

    private static function findMvhdDuration($handle, int $start, int $end): ?int
    {
        fseek($handle, $start);

        while (ftell($handle) < $end) {
            $boxStart = (int) ftell($handle);
            $parsed   = self::readBoxHeader($handle, $boxStart, $end);

            if ($parsed === null) {
                break;
            }

            if ($parsed['type'] === 'mvhd') {
                $duration = self::parseMvhd($handle, $parsed['content_start'], $parsed['content_end']);

                if ($duration !== null) {
                    return $duration;
                }
            }

            if (in_array($parsed['type'], self::CONTAINER_BOXES, true)) {
                $duration = self::findMvhdDuration($handle, $parsed['content_start'], $parsed['content_end']);

                if ($duration !== null) {
                    return $duration;
                }
            }

            fseek($handle, $parsed['content_end']);
        }

        return null;
    }

    /**
     * @return array{type: string, content_start: int, content_end: int}|null
     */
    private static function readBoxHeader($handle, int $boxStart, int $parentEnd): ?array
    {
        if ($boxStart + 8 > $parentEnd) {
            return null;
        }

        fseek($handle, $boxStart);
        $header = fread($handle, 8);

        if ($header === false || strlen($header) < 8) {
            return null;
        }

        $size = unpack('N', substr($header, 0, 4))[1];
        $type = substr($header, 4, 4);
        $headerSize = 8;

        if ($size === 1) {
            $extended = fread($handle, 8);

            if ($extended === false || strlen($extended) < 8) {
                return null;
            }

            $parts    = unpack('N2', $extended);
            $size     = ($parts[1] * 4294967296) + $parts[2];
            $headerSize = 16;
        } elseif ($size === 0) {
            $size = $parentEnd - $boxStart;
        }

        if ($size < $headerSize) {
            return null;
        }

        $contentEnd = $boxStart + $size;

        if ($contentEnd > $parentEnd) {
            $contentEnd = $parentEnd;
        }

        return [
            'type'          => $type,
            'content_start' => $boxStart + $headerSize,
            'content_end'   => $contentEnd,
        ];
    }

    private static function parseMvhd($handle, int $start, int $end): ?int
    {
        if ($start + 20 > $end) {
            return null;
        }

        fseek($handle, $start);

        $version = ord((string) fread($handle, 1));
        fread($handle, 3);

        if ($version === 1) {
            if ($start + 36 > $end) {
                return null;
            }

            fread($handle, 16);
            $timescaleData = fread($handle, 4);
            $durationData  = fread($handle, 8);
        } else {
            fread($handle, 8);
            $timescaleData = fread($handle, 4);
            $durationData  = fread($handle, 4);
        }

        if ($timescaleData === false || $durationData === false
            || strlen($timescaleData) < 4 || strlen($durationData) < 4) {
            return null;
        }

        $timescale = unpack('N', $timescaleData)[1];

        if ($version === 1) {
            $parts    = unpack('N2', $durationData);
            $duration = ($parts[1] * 4294967296) + $parts[2];
        } else {
            $duration = unpack('N', $durationData)[1];
        }

        if ($timescale <= 0 || $duration <= 0) {
            return null;
        }

        return (int) round($duration / $timescale);
    }
}
