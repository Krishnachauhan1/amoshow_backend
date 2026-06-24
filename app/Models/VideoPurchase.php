<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoPurchase extends Model
{
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $fillable = [
        'user_id',
        'video_id',
        'amount',
        'transaction_id',
        'status',
        'gateway',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
