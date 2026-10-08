<?php

namespace App\Support;

use App\Models\UploadChunk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ChunkAssembler
{
    public static function assemble(int $userId, string $uploadId): string
    {
        $chunks = UploadChunk::where('upload_id', $uploadId)
            ->where('user_id', $userId)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            throw new RuntimeException('No chunks found');
        }

        $totalChunks = (int) $chunks->first()->total_chunks;
        if ($chunks->count() < $totalChunks) {
            throw new RuntimeException(
                'Upload incomplete ('.$chunks->count().'/'.$totalChunks.')'
            );
        }

        foreach ($chunks as $chunk) {
            if (! Storage::disk('local')->exists($chunk->chunk_path)) {
                throw new RuntimeException('Chunk file missing: '.$chunk->chunk_index);
            }
        }

        $ext = pathinfo((string) $chunks->first()->original_filename, PATHINFO_EXTENSION) ?: 'mp4';
        $filename = 'videos/'.Str::uuid().'.'.$ext;
        $fullPath = storage_path('app/public/'.$filename);

        if (! file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        $dest = fopen($fullPath, 'wb');
        foreach ($chunks as $chunk) {
            $src = fopen(storage_path('app/'.$chunk->chunk_path), 'rb');
            stream_copy_to_stream($src, $dest);
            fclose($src);
        }
        fclose($dest);

        Storage::disk('local')->deleteDirectory('chunks/'.$uploadId);
        UploadChunk::where('upload_id', $uploadId)->delete();

        return $filename;
    }
}
