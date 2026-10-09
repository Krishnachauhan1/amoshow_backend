<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'price', 'duration_days', 'features'
    ];

    protected $casts = [
        'features' => 'array',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function descriptionText(): string
    {
        if (is_array($this->features) && $this->features !== []) {
            return implode(' • ', array_map('strval', $this->features));
        }

        return (string) ($this->features ?? '');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'duration_days' => (int) $this->duration_days,
            'features' => $this->features ?? [],
            'description' => $this->descriptionText(),
        ];
    }

    public static function ensureDefaults()
    {
        if (! Schema::hasTable('plans')) {
            return static::query()->whereRaw('1 = 0')->get();
        }

        if (static::query()->exists()) {
            return static::query()->orderBy('price')->get();
        }

        $defaults = [
            [
                'name' => 'Basic',
                'price' => 99,
                'duration_days' => 30,
                'features' => ['Ad-free viewing', '720p downloads'],
            ],
            [
                'name' => 'Pro',
                'price' => 299,
                'duration_days' => 30,
                'features' => ['All Basic perks', '4K playback', 'Priority support'],
            ],
            [
                'name' => 'Premium',
                'price' => 499,
                'duration_days' => 30,
                'features' => ['All Pro perks', 'Full library access', 'Early access to new titles'],
            ],
        ];

        foreach ($defaults as $plan) {
            static::query()->create($plan);
        }

        return static::query()->orderBy('price')->get();
    }
}