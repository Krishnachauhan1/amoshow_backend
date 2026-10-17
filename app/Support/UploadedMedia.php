<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class UploadedMedia
{
    public static function imageExtension(UploadedFile $file): ?string
    {
        $ext = static::clientExtension($file);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        $head = static::headHex($file, 16);
        if ($head === null) {
            return null;
        }

        return match (true) {
            str_starts_with($head, 'ffd8ff') => 'jpg',
            str_starts_with($head, '89504e47') => 'png',
            str_contains($head, '57454250') => 'webp',
            str_starts_with($head, '47494638') => 'gif',
            default => null,
        };
    }

    public static function videoExtension(UploadedFile $file): ?string
    {
        $ext = static::clientExtension($file);
        $allowed = ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'qt'];
        if (in_array($ext, $allowed, true)) {
            return $ext === 'qt' ? 'mov' : $ext;
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $bytes = (string) fread($handle, 32);
        fclose($handle);

        if (str_contains($bytes, 'ftyp')) {
            return 'mp4';
        }
        $hex = bin2hex($bytes);
        if (str_starts_with($hex, '1a45dfa3')) {
            return 'webm';
        }
        if (str_starts_with($hex, '52494646')) {
            return 'avi';
        }

        return null;
    }

    public static function store(UploadedFile $file, string $folder, string $prefix, string $ext): string|false
    {
        return $file->storeAs(
            $folder,
            uniqid($prefix, true).'.'.$ext,
            'public'
        );
    }

    private static function clientExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== '') {
            return $ext;
        }

        $name = strtolower((string) $file->getClientOriginalName());
        $dot = strrpos($name, '.');
        if ($dot === false) {
            return '';
        }

        return substr($name, $dot + 1);
    }

    private static function headHex(UploadedFile $file, int $length): ?string
    {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = bin2hex((string) fread($handle, $length));
        fclose($handle);

        return $head;
    }
}
