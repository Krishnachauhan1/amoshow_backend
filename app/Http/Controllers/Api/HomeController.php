<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Models\Video;
use App\Support\HomeContentSections;
use App\Support\HomeSectionLayoutResolver;
use App\Support\YoutubeFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        try {
            return response()->json($this->buildHomePayload());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'banners' => [],
                'sections' => [],
                'message' => app()->isProduction()
                    ? 'Home API error'
                    : $e->getMessage(),
            ]);
        }
    }

    private function buildHomePayload(): array
    {
        $banners = [];
        if (Schema::hasTable('home_banners')) {
            $banners = HomeBanner::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'title', 'subtitle', 'image_path', 'link_url', 'sort_order'])
                ->values();
        }

        $sections = [];
        $feedSection = $this->featuredFeedSection();
        if ($feedSection) {
            $sections[] = $feedSection;
        }

        foreach (HomeContentSections::all() as $key => $config) {
            if (!Schema::hasTable($config['table'])) {
                continue;
            }

            try {
                $model = $config['model'];
                $items = $model::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($item) => $item->toHomePayload())
                    ->values();

                if ($items->isEmpty()) {
                    continue;
                }

                $size = HomeSectionLayoutResolver::resolve($key);

                $sections[] = [
                    'key' => $key,
                    'title' => $config['title'],
                    'type' => $config['type'],
                    'card_width' => $size['card_width'],
                    'card_height' => $size['card_height'],
                    'items' => $items,
                ];
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
        }

        return [
            'banners' => $banners,
            'sections' => $sections,
        ];
    }

    private function featuredFeedSection(): ?array
    {
        try {
            Video::ensureShowOnHomeColumn();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        if (! Schema::hasColumn('videos', 'show_on_home')) {
            return null;
        }

        $query = Video::query()
            ->with('channel')
            ->where('show_on_home', true)
            ->where('status', 'published');

        if (Schema::hasColumn('videos', 'visibility')) {
            $query->where(function ($q) {
                $q->whereNull('visibility')->orWhere('visibility', 'public');
            });
        }

        try {
            $videos = $query->latest('updated_at')->limit(20)->get();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }

        if ($videos->isEmpty()) {
            return null;
        }

        return [
            'key' => 'feed',
            'title' => 'From the feed',
            'type' => 'video',
            'card_width' => 200,
            'card_height' => 300,
            'items' => $videos->map(fn (Video $video) => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'thumbnail' => YoutubeFormatter::storageUrl($video->thumbnail),
                'video_path' => YoutubeFormatter::storageUrl($video->video_path),
                'video_url' => YoutubeFormatter::storageUrl($video->video_path),
                'channel_id' => $video->channel_id,
                'channel' => $video->channel ? [
                    'id' => $video->channel->id,
                    'name' => $video->channel->name,
                ] : null,
                'is_feed_video' => true,
                'type' => 'video',
                'sort_order' => 0,
            ])->values(),
        ];
    }
}
