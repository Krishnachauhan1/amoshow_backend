<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Support\VideoProbe;
use Illuminate\Console\Command;

class BackfillVideoDurations extends Command
{
    protected $signature = 'videos:backfill-duration {--platform= : ott, youtube, or shorts}';

    protected $description = 'Probe and set duration for videos missing duration';

    public function handle(): int
    {
        $query = Video::query()
            ->where(fn ($q) => $q->whereNull('duration')->orWhere('duration', 0));

        if ($platform = $this->option('platform')) {
            $query->where('platform', $platform);
        }

        $updated = 0;
        $skipped = 0;

        $query->eachById(function (Video $video) use (&$updated, &$skipped) {
            $path = storage_path('app/public/' . $video->video_path);

            $duration = VideoProbe::durationSeconds($path);

            if (!$duration) {
                $this->warn("Could not probe video #{$video->id}: {$video->video_path}");
                $skipped++;

                return;
            }

            $video->update(['duration' => $duration]);
            $this->line("Video #{$video->id}: {$duration}s");
            $updated++;
        });

        $this->info("Updated: {$updated}, skipped: {$skipped}");

        return self::SUCCESS;
    }
}
