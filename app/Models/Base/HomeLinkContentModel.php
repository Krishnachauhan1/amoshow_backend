<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

abstract class HomeLinkContentModel extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image_path',
        'action_url',
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
            'thumbnail' => $this->image_path,
            'video_path' => null,
            'action_url' => $this->action_url,
            'type' => 'link',
            'sort_order' => $this->sort_order,
        ];
    }
}
