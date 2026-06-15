<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Support\VideoThumbnail;
use Illuminate\Console\Command;

class BackfillShortThumbnails extends Command
{
    protected $signature = 'shorts:backfill-thumbnails';

    protected $description = 'Generate thumbnails for shorts that are missing them';

    public function handle(): int
    {
        if (! VideoThumbnail::hasFfmpeg()) {
            $this->error('ffmpeg is not installed on this server.');
            $this->line('Hostinger shared hosting usually does not include ffmpeg.');
            $this->line('Options:');
            $this->line('  1) Re-upload shorts from the updated mobile app (thumbnail is sent from phone).');
            $this->line('  2) Run: php artisan shorts:set-thumbnail {videoId} /path/to/image.jpg');
            $this->line('  3) Install a static ffmpeg binary in ~/bin/ffmpeg');

            return self::FAILURE;
        }

        $updated = 0;
        $skipped = 0;

        Video::shorts()
            ->where(fn ($q) => $q->whereNull('thumbnail')->orWhere('thumbnail', ''))
            ->eachById(function (Video $video) use (&$updated, &$skipped) {
                $path = storage_path('app/public/' . $video->video_path);

                if (!is_file($path)) {
                    $this->warn("Video file missing #{$video->id}");
                    $skipped++;

                    return;
                }

                $thumb = VideoThumbnail::generateFromVideo($path);

                if (!$thumb) {
                    $this->warn("Could not generate thumb #{$video->id} (ffmpeg required)");
                    $skipped++;

                    return;
                }

                $video->update(['thumbnail' => $thumb]);
                $this->line("Video #{$video->id}: {$thumb}");
                $updated++;
            });

        $this->info("Updated: {$updated}, skipped: {$skipped}");

        return self::SUCCESS;
    }
}
