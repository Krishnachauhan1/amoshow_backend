<?php

namespace App\Support;

use App\Models\HomeGame;
use App\Models\HomeMovie;
use App\Models\HomeMusic;
use App\Models\HomeNews;
use App\Models\HomeProduct;
use App\Models\HomeTop10Movie;
use App\Models\HomeTop10Music;
use App\Models\HomeTopChannel;
use App\Models\HomeTvSeries;

class HomeContentSections
{
    /** @return array<string, array{model: class-string, title: string, type: string, table: string, card_width: int, card_height: int}> */
    public static function all(): array
    {
        return [
            'music' => [
                'model' => HomeMusic::class,
                'title' => 'Music',
                'type' => 'video',
                'table' => 'home_music',
                'card_width' => 200,
                'card_height' => 300,
            ],
            'top10_music' => [
                'model' => HomeTop10Music::class,
                'title' => 'Top 10 Music',
                'type' => 'video',
                'table' => 'home_top10_music',
                'card_width' => 160,
                'card_height' => 240,
            ],
            'movies' => [
                'model' => HomeMovie::class,
                'title' => 'Movies',
                'type' => 'video',
                'table' => 'home_movies',
                'card_width' => 220,
                'card_height' => 330,
            ],
            'top10_movies' => [
                'model' => HomeTop10Movie::class,
                'title' => 'Top 10 Movies',
                'type' => 'video',
                'table' => 'home_top10_movies',
                'card_width' => 160,
                'card_height' => 240,
            ],
            'tv_series' => [
                'model' => HomeTvSeries::class,
                'title' => 'TV Series',
                'type' => 'video',
                'table' => 'home_tv_series',
                'card_width' => 200,
                'card_height' => 300,
            ],
            'products' => [
                'model' => HomeProduct::class,
                'title' => 'Product',
                'type' => 'link',
                'table' => 'home_products',
                'card_width' => 140,
                'card_height' => 180,
            ],
            'games' => [
                'model' => HomeGame::class,
                'title' => 'Games',
                'type' => 'link',
                'table' => 'home_games',
                'card_width' => 120,
                'card_height' => 220,
            ],
            'news' => [
                'model' => HomeNews::class,
                'title' => 'News',
                'type' => 'video',
                'table' => 'home_news',
                'card_width' => 280,
                'card_height' => 160,
            ],
            'top_channels' => [
                'model' => HomeTopChannel::class,
                'title' => 'Top Channel',
                'type' => 'link',
                'table' => 'home_top_channels',
                'card_width' => 120,
                'card_height' => 120,
            ],
        ];
    }

    /** @return array{card_width: int, card_height: int} */
    public static function cardSize(string $key): array
    {
        $config = self::get($key);
        if (!$config) {
            return ['card_width' => 200, 'card_height' => 300];
        }

        return [
            'card_width' => (int) ($config['card_width'] ?? 200),
            'card_height' => (int) ($config['card_height'] ?? 300),
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
