<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Support\HomeContentSections;
use App\Support\HomeSectionLayoutResolver;
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
}
