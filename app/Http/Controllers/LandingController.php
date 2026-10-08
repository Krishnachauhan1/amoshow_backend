<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Plan;
use App\Models\Video;
use App\Support\YoutubeFormatter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing', [
            'featured' => $this->safeCollect(fn () => $this->featuredVideos()),
            'shorts' => $this->safeCollect(fn () => $this->shorts()),
            'channels' => $this->safeCollect(fn () => $this->channels()),
            'plans' => $this->safeCollect(fn () => $this->plans()),
        ]);
    }

    private function featuredVideos()
    {
        if (! Schema::hasTable('videos')) {
            return collect();
        }

        $query = Video::query()
            ->with('channel:id,name')
            ->where('status', 'published')
            ->latest()
            ->limit(10);

        if (Schema::hasColumn('videos', 'platform')) {
            $query->whereIn('platform', ['ott', 'youtube']);
        }

        return $query->get()->map(fn (Video $video) => $this->mapVideo($video));
    }

    private function shorts()
    {
        if (! Schema::hasTable('videos') || ! Schema::hasColumn('videos', 'platform')) {
            return collect();
        }

        return Video::query()
            ->with('channel:id,name')
            ->where('status', 'published')
            ->where('platform', 'shorts')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Video $video) => $this->mapVideo($video));
    }

    private function channels()
    {
        if (! Schema::hasTable('channels')) {
            return collect();
        }

        $query = Channel::query()->limit(8);

        if (Schema::hasColumn('channels', 'subscriber_count')) {
            $query->orderByDesc('subscriber_count');
        } else {
            $query->latest();
        }

        return $query->get()->map(function (Channel $channel) {
            return [
                'name' => $channel->name,
                'description' => $channel->description,
                'avatar' => YoutubeFormatter::storageUrl($channel->avatar ?? null),
                'subscribers' => YoutubeFormatter::subscribers((int) ($channel->subscriber_count ?? 0)),
            ];
        });
    }

    private function plans()
    {
        if (! Schema::hasTable('plans')) {
            return collect();
        }

        return Plan::query()->orderBy('price')->get();
    }

    private function mapVideo(Video $video): array
    {
        return [
            'title' => $video->title,
            'type' => $video->type,
            'platform' => $video->platform ?? 'ott',
            'thumbnail' => YoutubeFormatter::storageUrl($video->thumbnail),
            'duration' => YoutubeFormatter::duration($video->duration ? (int) $video->duration : null),
            'views' => YoutubeFormatter::views((int) ($video->views_count ?? 0)),
            'channel' => $video->channel?->name,
            'premium' => (bool) $video->is_premium,
        ];
    }

    private function safeCollect(callable $callback)
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }
}
