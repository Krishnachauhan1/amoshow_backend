<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSectionLayout extends Model
{
    protected $fillable = [
        'section_key',
        'card_width',
        'card_height',
    ];

    protected $casts = [
        'card_width' => 'integer',
        'card_height' => 'integer',
    ];
}
