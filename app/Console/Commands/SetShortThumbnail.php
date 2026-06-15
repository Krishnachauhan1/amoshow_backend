<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SetShortThumbnail extends Command
{
    protected $signature = 'shorts:set-thumbnail {videoId} {imagePath}';

    protected $description = 'Manually attach a JPEG/PNG thumbnail to a short (no ffmpeg needed)';

    public function handle(): int
    {
        $video = Video::shorts()->find($this->argument('videoId'));

        if (! $video) {
            $this->error('Short not found.');

            return self::FAILURE;
        }

        $source = $this->argument('imagePath');

        if (! is_file($source)) {
            $this->error("Image not found: {$source}");

            return self::FAILURE;
        }

        $ext      = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
        if (! in_array($ext, $allowed, true)) {
            $this->error('Use jpg, jpeg, png, or webp.');

            return self::FAILURE;
        }

        $relative = 'thumbnails/' . Str::uuid() . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $dest     = storage_path('app/public/' . $relative);

        File::ensureDirectoryExists(dirname($dest));
        File::copy($source, $dest);

        $video->update(['thumbnail' => $relative]);

        $this->info("Short #{$video->id} thumbnail set to {$relative}");

        return self::SUCCESS;
    }
}
