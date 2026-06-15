<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'category_id',
        'sort_order',
        'is_active',
        'limit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'limit' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class, 'home_section_video')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderBy('home_section_video.sort_order');
    }
}

