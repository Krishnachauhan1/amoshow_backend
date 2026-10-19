<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\Video;
use App\Support\YoutubeFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

    public function watch(string $id): Response
    {
        $video = $this->findVideo($id);

        return $this->openAppResponse(
            kind: 'watch',
            id: $id,
            title: $video?->title ?? 'Watch on AMOSHWZ',
            thumbnail: YoutubeFormatter::storageUrl($video?->thumbnail),
        );
    }

    public function short(string $id): Response
    {
        $video = $this->findVideo($id);

        return $this->openAppResponse(
            kind: 'shorts',
            id: $id,
            title: $video?->title ?? 'Watch this short on AMOSHWZ',
            thumbnail: YoutubeFormatter::storageUrl($video?->thumbnail),
        );
    }

    public function wellKnownAssetLinks(): Response
    {
        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.amoshwz',
                    'sha256_cert_fingerprints' => [
                        'REPLACE_WITH_UPLOAD_KEYSTORE_SHA256',
                    ],
                ],
            ],
        ]);
    }

    public function wellKnownAppleAppSite(): Response
    {
        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appID' => 'D2C6ZJ6XPC.com.amoshwz',
                    'paths' => ['/watch/*', '/shorts/*', '/ref/*'],
                ]],
            ],
        ]);
    }

    public function referral(Request $request, string $code): Response
    {
        $code = Referral::normalizeCode($code);
        $referrer = Referral::findReferrer($code);

        $response = response()->view('referral', [
            'code' => $code !== '' ? $code : 'AM',
            'referrerName' => $referrer?->name ?? 'A creator',
            'valid' => $referrer !== null,
        ]);

        if ($referrer) {
            $response->cookie('amoshwz_ref', Referral::codeFor($referrer), 60 * 24 * 30);
        }

        return $response;
    }

    private function findVideo(string $id): ?Video
    {
        if (! Schema::hasTable('videos') || ! ctype_digit($id)) {
            return null;
        }

        try {
            return Video::query()->find((int) $id);
        } catch (\Throwable) {
            return null;
        }
    }

    private function openAppResponse(string $kind, string $id, string $title, ?string $thumbnail): Response
    {
        return response()->view('open_app', [
            'kind' => $kind,
            'id' => $id,
            'title' => $title,
            'thumbnail' => $thumbnail,
            'appUri' => 'amoshwz://'.$kind.'/'.$id,
            'playStoreUrl' => 'https://play.google.com/store/apps/details?id=com.amoshwz',
            'appStoreUrl' => 'https://apps.apple.com/app/amoshwz',
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
