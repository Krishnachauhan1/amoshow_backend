<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

abstract class HomeVideoContentModel extends Model
{
    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'video_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function toHomePayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'video_path' => $this->video_path,
            'action_url' => null,
            'type' => 'video',
            'sort_order' => $this->sort_order,
        ];
    }
}
