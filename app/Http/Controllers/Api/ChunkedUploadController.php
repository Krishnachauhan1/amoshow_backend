<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UploadChunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Video;
use App\Support\VideoProbe;

class ChunkedUploadController extends Controller
{
    public function initiate(Request $request)
    {
        $request->validate([
            'filename'     => 'required|string',
            'total_chunks' => 'required|integer|min:1|max:500',
            'title'        => 'required|string',
        ]);

        return response()->json([
            'upload_id'    => (string) Str::uuid(),
            'total_chunks' => $request->total_chunks,
        ]);
    }

    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_id'    => 'required|string',
            'chunk_index'  => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'chunk'        => 'required|file',
            'filename'     => 'required|string',
        ]);

        if (!$request->user()->channel) {
            return response()->json(['message' => 'Create a channel first'], 422);
        }

        $chunkPath = $request->file('chunk')->storeAs(
            'chunks/' . $request->upload_id,
            'chunk_' . $request->chunk_index,
            'local'
        );

        UploadChunk::updateOrCreate(
            [
                'upload_id'   => $request->upload_id,
                'chunk_index' => $request->chunk_index,
            ],
            [
                'user_id'           => $request->user()->id,
                'total_chunks'      => $request->total_chunks,
                'chunk_path'        => $chunkPath,
                'original_filename' => $request->filename,
            ]
        );

        $uploaded = UploadChunk::where('upload_id', $request->upload_id)->count();

        return response()->json([
            'chunk_index' => $request->chunk_index,
            'uploaded'    => $uploaded,
            'total'       => $request->total_chunks,
            'percentage'  => (int) (($uploaded / $request->total_chunks) * 100),
        ]);
    }



public function finalize(Request $request)
{
    $request->validate([
        'upload_id' => [
            'required',
            'string',
            Rule::exists('upload_chunks', 'upload_id')
                ->where('user_id', $request->user()->id),
        ],

        'title'          => 'required|string|max:255',
        'description'    => 'nullable|string|max:1000',

        'type'           => 'required|in:movie,series,episode',
        'is_premium'     => 'required|boolean',

        'series_id'        => 'nullable|required_if:type,episode|exists:videos,id',
        'episode_number'   => 'nullable|required_if:type,episode|integer|min:1',
        'duration_seconds' => 'nullable|integer|min:1',
    ]);

    DB::beginTransaction();

    try {

        $chunks = UploadChunk::where('upload_id', $request->upload_id)
            ->where('user_id', $request->user()->id)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return response()->json(['message' => 'No chunks found'], 422);
        }

        $totalChunks = $chunks->first()->total_chunks;

        if ($chunks->count() < $totalChunks) {
            return response()->json([
                'message'  => 'Upload incomplete',
                'uploaded' => $chunks->count(),
                'total'    => $totalChunks,
            ], 422);
        }

        // Ensure all chunk files exist
        foreach ($chunks as $chunk) {
            if (!Storage::disk('local')->exists($chunk->chunk_path)) {
                return response()->json([
                    'message' => 'Chunk file missing: ' . $chunk->chunk_index
                ], 422);
            }
        }

        // Create final file
        $ext      = pathinfo($chunks->first()->original_filename, PATHINFO_EXTENSION);
        $filename = 'videos/' . Str::uuid() . '.' . $ext;
        $fullPath = storage_path('app/public/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        $dest = fopen($fullPath, 'wb');

        foreach ($chunks as $chunk) {
            $src = fopen(storage_path('app/' . $chunk->chunk_path), 'rb');
            stream_copy_to_stream($src, $dest);
            fclose($src);
        }

        fclose($dest);

        // Cleanup chunks
        Storage::disk('local')->deleteDirectory('chunks/' . $request->upload_id);
        UploadChunk::where('upload_id', $request->upload_id)->delete();

        // Extra logical validation
        if ($request->type === 'episode' && !$request->series_id) {
            return response()->json([
                'message' => 'Series ID is required for episode'
            ], 422);
        }

        $duration = $request->integer('duration_seconds')
            ?: VideoProbe::durationSeconds($fullPath);

        // Create video
        $video = $request->user()->channel->videos()->create([
            'title'          => $request->title,
            'description'    => $request->description,
            'video_path'     => $filename,
            'type'           => $request->type,
            'duration'       => $duration,
            'is_premium'     => $request->boolean('is_premium'),
            'series_id'      => $request->series_id,
            'episode_number' => $request->episode_number,
            'status'         => 'published',
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Video uploaded successfully!',
            'video'   => $video
        ], 201);

    } catch (\Throwable $e) {

        DB::rollBack();

        return response()->json([
            'message' => 'Upload failed',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
}