<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Support\YoutubeFormatter;
use Illuminate\Console\Command;

class CheckMediaFiles extends Command
{
    protected $signature = 'media:check {--shorts : Only shorts}';

    protected $description = 'Check if video/thumbnail files exist on disk vs API URLs';

    public function handle(): int
    {
        $this->line('PUBLIC_STORAGE_PREFIX: ' . env('PUBLIC_STORAGE_PREFIX', 'public/storage'));
        $this->line('APP_URL: ' . config('app.url'));
        $this->line('public/storage link: ' . (is_link(public_path('storage')) ? 'yes' : 'no'));
        $this->newLine();

        $query = Video::query()->whereNotNull('thumbnail')->where('thumbnail', '!=', '');

        if ($this->option('shorts')) {
            $query->shorts();
        }

        $query->limit(10)->each(function (Video $video) {
            $diskPath = storage_path('app/public/' . $video->thumbnail);
            $exists   = is_file($diskPath);
            $url      = YoutubeFormatter::storageUrl($video->thumbnail);

            $this->line(sprintf(
                '#%d %s | disk: %s | url: %s',
                $video->id,
                $exists ? 'OK' : 'MISSING',
                $video->thumbnail,
                $url
            ));
        });

        return self::SUCCESS;
    }
}
