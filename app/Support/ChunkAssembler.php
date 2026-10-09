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

        $resolved = [];
        foreach ($chunks as $chunk) {
            $resolved[] = [
                'index' => $chunk->chunk_index,
                'path' => self::absolutePath((string) $chunk->chunk_path, (int) $chunk->chunk_index),
            ];
        }

        $ext = pathinfo((string) $chunks->first()->original_filename, PATHINFO_EXTENSION) ?: 'mp4';
        $filename = 'videos/'.Str::uuid().'.'.$ext;

        Storage::disk('public')->makeDirectory('videos');
        $fullPath = Storage::disk('public')->path($filename);

        $dest = fopen($fullPath, 'wb');
        if ($dest === false) {
            throw new RuntimeException('Could not create assembled video file');
        }

        try {
            foreach ($resolved as $chunk) {
                $src = fopen($chunk['path'], 'rb');
                if ($src === false) {
                    throw new RuntimeException('Chunk file missing: '.$chunk['index']);
                }
                stream_copy_to_stream($src, $dest);
                fclose($src);
            }
        } finally {
            fclose($dest);
        }

        Storage::disk('local')->deleteDirectory('chunks/'.$uploadId);
        UploadChunk::where('upload_id', $uploadId)->delete();

        return $filename;
    }

    public static function absolutePath(string $relative, ?int $index = null): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        $candidates = [
            Storage::disk('local')->path($relative),
            storage_path('app/private/'.$relative),
            storage_path('app/'.$relative),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Chunk file missing: '.($index ?? $relative));
    }
}
