<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'upload_id', 'chunk_index',
        'total_chunks', 'chunk_path', 'original_filename'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}