<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\UploadChunk;
use App\Models\Video;
use App\Support\ChunkAssembler;
use App\Support\VideoProbe;
use App\Support\YoutubeFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudioController extends Controller
{
    private const GENRES = [
        'Technology', 'Music', 'Gaming', 'Sports', 'Comedy',
        'Travel', 'Food', 'Health', 'Education', 'News',
        'Entertainment', 'Finance', 'Lifestyle', 'Science', 'Other',
    ];

    private const VIDEO_EXTS = ['mp4', 'mov', 'avi', '3gp', '3gpp', 'webm'];

    private const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp'];

    public function index(Request $request): View
    {
        $channel = $request->user()->channel;
        $videos = Video::query()
            ->when(
                $channel,
                fn ($q) => $q->where('channel_id', $channel->id),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->latest()
            ->paginate(12)
            ->through(function (Video $video) {
                return (object) [
                    'id' => $video->id,
                    'title' => $video->title,
                    'platform' => $video->platform ?: 'youtube',
                    'visibility' => $video->visibility ?: 'public',
                    'status' => $video->status ?: 'published',
                    'views' => YoutubeFormatter::views((int) ($video->views_count ?? 0)),
                    'duration' => YoutubeFormatter::duration($video->duration ? (int) $video->duration : null),
                    'thumbnail' => YoutubeFormatter::storageUrl($video->thumbnail),
                    'created' => optional($video->created_at)?->diffForHumans(),
                ];
            });

        return view('studio.index', [
            'videos' => $videos,
            'channel' => $channel,
        ]);
    }

    public function create(): View
    {
        return view('studio.upload', [
            'genres' => self::GENRES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'kind' => 'required|in:video,short',
            'video' => 'required|file|max:512000',
            'thumbnail' => 'nullable|file|max:4096',
            'visibility' => 'nullable|in:public,private,unlisted',
            'genre' => 'nullable|string|max:50',
        ]);

        if (! $this->isAllowedExt($request->file('video')->getClientOriginalExtension(), self::VIDEO_EXTS)) {
            return back()->withInput()->withErrors(['video' => 'Upload an MP4, MOV, AVI or WebM video.']);
        }
        if ($request->hasFile('thumbnail') && ! $this->isAllowedExt($request->file('thumbnail')->getClientOriginalExtension(), self::IMAGE_EXTS)) {
            return back()->withInput()->withErrors(['thumbnail' => 'Thumbnail must be JPG, PNG or WebP.']);
        }

        $folder = $request->input('kind') === 'short' ? 'shorts' : 'videos';
        $videoPath = $request->file('video')->store($folder, 'public');
        $thumbPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('thumbnails', 'public')
            : null;

        $this->publishVideo($request, $videoPath, $thumbPath);

        return redirect()
            ->route('studio.index')
            ->with('status', 'Video uploaded.');
    }

    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string',
            'total_chunks' => 'required|integer|min:1|max:500',
            'title' => 'required|string|max:255',
        ]);

        if (! $this->isAllowedExt(pathinfo($request->filename, PATHINFO_EXTENSION), self::VIDEO_EXTS)) {
            return response()->json(['message' => 'Upload an MP4, MOV, AVI or WebM video.'], 422);
        }
        $this->ensureChannel($request);

        return response()->json([
            'upload_id' => (string) Str::uuid(),
            'total_chunks' => (int) $request->total_chunks,
        ]);
    }

    public function chunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'chunk' => 'required|file',
            'filename' => 'required|string',
        ]);

        $this->ensureChannel($request);
        Storage::disk('local')->makeDirectory('chunks/'.$request->upload_id);

        $chunkPath = $request->file('chunk')->storeAs(
            'chunks/'.$request->upload_id,
            'chunk_'.$request->chunk_index,
            'local'
        );

        if (! $chunkPath || ! Storage::disk('local')->exists($chunkPath)) {
            return response()->json(['message' => 'Could not save upload chunk'], 500);
        }

        UploadChunk::updateOrCreate(
            [
                'upload_id' => $request->upload_id,
                'chunk_index' => $request->chunk_index,
            ],
            [
                'user_id' => $request->user()->id,
                'total_chunks' => $request->total_chunks,
                'chunk_path' => $chunkPath,
                'original_filename' => $request->filename,
            ]
        );

        $uploaded = UploadChunk::where('upload_id', $request->upload_id)->count();

        return response()->json([
            'uploaded' => $uploaded,
            'total' => (int) $request->total_chunks,
            'percentage' => (int) (($uploaded / $request->total_chunks) * 100),
        ]);
    }

    public function finalize(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => [
                'required',
                'string',
                Rule::exists('upload_chunks', 'upload_id')->where('user_id', $request->user()->id),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'kind' => 'required|in:video,short',
            'thumbnail' => 'nullable|file|max:4096',
            'visibility' => 'nullable|in:public,private,unlisted',
            'genre' => 'nullable|string|max:50',
        ]);

        if ($request->hasFile('thumbnail') && ! $this->isAllowedExt($request->file('thumbnail')->getClientOriginalExtension(), self::IMAGE_EXTS)) {
            return response()->json(['message' => 'Thumbnail must be JPG, PNG or WebP.'], 422);
        }

        try {
            $videoPath = ChunkAssembler::assemble(
                (int) $request->user()->id,
                (string) $request->input('upload_id')
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $thumbPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('thumbnails', 'public')
            : null;

        $this->publishVideo($request, $videoPath, $thumbPath);

        return response()->json([
            'message' => 'Video uploaded.',
            'redirect' => route('studio.index'),
        ], 201);
    }

    public function destroy(Request $request, Video $video): RedirectResponse
    {
        $channel = $request->user()->channel;
        if (! $channel || (int) $video->channel_id !== (int) $channel->id) {
            abort(403);
        }

        if ($video->video_path) {
            Storage::disk('public')->delete($video->video_path);
        }
        if ($video->thumbnail) {
            Storage::disk('public')->delete($video->thumbnail);
        }
        $video->delete();

        return back()->with('status', 'Upload removed.');
    }

    private function publishVideo(Request $request, string $videoPath, ?string $thumbPath): Video
    {
        $channel = $this->ensureChannel($request);
        $kind = $request->input('kind', 'video');
        $platform = $kind === 'short' ? 'shorts' : 'youtube';
        $fullPath = storage_path('app/public/'.$videoPath);
        $duration = VideoProbe::durationSeconds($fullPath);

        if ($platform === 'shorts' && $duration && $duration > 180) {
            Storage::disk('public')->delete($videoPath);
            if ($thumbPath) {
                Storage::disk('public')->delete($thumbPath);
            }
            throw \Illuminate\Validation\ValidationException::withMessages([
                'video' => 'Shorts must be 180 seconds or less.',
            ]);
        }

        return $channel->videos()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'video_path' => $videoPath,
            'thumbnail' => $thumbPath,
            'type' => 'movie',
            'platform' => $platform,
            'visibility' => $request->input('visibility', 'public'),
            'genre' => $request->input('genre', 'Other'),
            'duration' => $duration,
            'comments_enabled' => true,
            'downloadable' => $platform !== 'shorts',
            'status' => 'published',
        ]);
    }

    private function ensureChannel(Request $request)
    {
        $user = $request->user();
        $channel = $user->channel;
        if ($channel) {
            return $channel;
        }

        return $user->channel()->create([
            'name' => ($user->name ?: 'My').' Channel',
        ]);
    }

    private function isAllowedExt(string $ext, array $allowed): bool
    {
        return in_array(strtolower($ext), $allowed, true);
    }
}
