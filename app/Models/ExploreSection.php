<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExploreSection extends Model
{
    protected $fillable = ['emoji', 'title', 'slug', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
