<?php

namespace App\Support;

use App\Models\HomeSectionLayout;
use Illuminate\Support\Facades\Schema;

class HomeSectionLayoutResolver
{
    /** @return array{card_width: int, card_height: int} */
    public static function resolve(string $sectionKey): array
    {
        $defaults = HomeContentSections::cardSize($sectionKey);

        if (!Schema::hasTable('home_section_layouts')) {
            return $defaults;
        }

        $layout = HomeSectionLayout::query()
            ->where('section_key', $sectionKey)
            ->first(['card_width', 'card_height']);

        if (!$layout) {
            return $defaults;
        }

        return [
            'card_width' => max(80, (int) $layout->card_width),
            'card_height' => max(80, (int) $layout->card_height),
        ];
    }

    /** @return array<string, array{card_width: int, card_height: int}> */
    public static function allForAdmin(): array
    {
        $out = [];
        foreach (HomeContentSections::all() as $key => $config) {
            $resolved = self::resolve($key);
            $out[$key] = [
                'title' => $config['title'],
                'type' => $config['type'],
                'card_width' => $resolved['card_width'],
                'card_height' => $resolved['card_height'],
                'default_width' => (int) $config['card_width'],
                'default_height' => (int) $config['card_height'],
            ];
        }

        return $out;
    }
}
