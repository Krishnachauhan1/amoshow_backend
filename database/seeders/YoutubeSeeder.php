<?php

namespace Database\Seeders;

use App\Models\ExploreSection;
use App\Models\YoutubeCategory;
use Illuminate\Database\Seeder;

class YoutubeSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['emoji' => '🔥', 'title' => 'Trending', 'slug' => 'trending', 'sort_order' => 1],
            ['emoji' => '🎵', 'title' => 'Music', 'slug' => 'music', 'sort_order' => 2],
            ['emoji' => '🎮', 'title' => 'Gaming', 'slug' => 'gaming', 'sort_order' => 3],
            ['emoji' => '📰', 'title' => 'News', 'slug' => 'news', 'sort_order' => 4],
            ['emoji' => '🎬', 'title' => 'Movies', 'slug' => 'movies', 'sort_order' => 5],
            ['emoji' => '😂', 'title' => 'Comedy', 'slug' => 'comedy', 'sort_order' => 6],
            ['emoji' => '⚽', 'title' => 'Sports', 'slug' => 'sports', 'sort_order' => 7],
            ['emoji' => '💻', 'title' => 'Technology', 'slug' => 'technology', 'sort_order' => 8],
        ];

        foreach ($sections as $section) {
            ExploreSection::updateOrCreate(['slug' => $section['slug']], $section);
        }

        $categories = [
            'Music', 'Gaming', 'News', 'Live', 'Comedy', 'Tech', 'Sports', 'Movies',
        ];

        foreach ($categories as $i => $name) {
            YoutubeCategory::updateOrCreate(
                ['slug' => strtolower($name)],
                ['name' => $name, 'sort_order' => $i + 1]
            );
        }
    }
}
