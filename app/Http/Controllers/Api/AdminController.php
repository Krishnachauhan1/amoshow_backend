<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use App\Support\VideoProbe;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function stats()
    {
        return response()->json([
            'total_users'         => User::count(),
            'active_subscribers'  => Subscription::where('status', 'active')
                                        ->where('ends_at', '>', now())->count(),
            'total_videos'        => Video::where('status', 'published')->count(),
            'total_channels'      => Channel::count(),
            'revenue_this_month'  => Payment::where('status', 'success')
                                        ->whereMonth('created_at', now()->month)
                                        ->sum('amount'),
            'new_users_this_week' => User::whereBetween('created_at', [
                                        now()->startOfWeek(), now()
                                    ])->count(),
        ]);
    }

    public function users(Request $request)
    {
        $users = User::with('subscriptions')
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->latest()
            ->paginate(25);

        return response()->json($users);
    }

    public function updateSubscription(Request $request, User $user)
    {
        $request->validate([
            'action'  => 'required|in:activate,cancel',
            'plan_id' => 'required_if:action,activate|exists:plans,id',
        ]);

        if ($request->action === 'cancel') {
            $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);
            return response()->json(['message' => 'Subscription cancelled']);
        }

        $plan = Plan::find($request->plan_id);
        $user->subscriptions()->create([
            'plan_id'   => $plan->id,
            'status'    => 'active',
            'starts_at' => now(),
            'ends_at'   => now()->addDays($plan->duration_days),
        ]);

        return response()->json(['message' => 'Subscription activated']);
    }

    public function videos(Request $request)
    {
        $videos = Video::with('channel.user')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25);

        return response()->json($videos);
    }

    public function updateVideoStatus(Request $request, Video $video)
    {
        $request->validate(['status' => 'required|in:published,draft,processing']);
        $video->update(['status' => $request->status]);
        return response()->json(['message' => 'Status updated', 'video' => $video]);
    }

    public function deleteVideo(Video $video)
    {
        Storage::disk('public')->delete($video->video_path);
        if ($video->thumbnail) Storage::disk('public')->delete($video->thumbnail);
        $video->delete();
        return response()->json(['message' => 'Video deleted']);
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string',
            'price'         => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'features'      => 'nullable|array',
        ]);

        return response()->json(Plan::create($data), 201);
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name'          => 'sometimes|string',
            'price'         => 'sometimes|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'features'      => 'nullable|array',
        ]);

        $plan->update($data);
        return response()->json($plan);
    }

    public function deletePlan(Plan $plan)
    {
        $plan->delete();
        return response()->json(['message' => 'Plan deleted']);
    }

    public function revenue()
    {
        $payments = Payment::where('status', 'success')
            ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(amount) as total, COUNT(*) as count')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
            ->take(12)
            ->get();

        return response()->json($payments);
    }

    public function registerAdmin(Request $request)
{
    if (User::where('role', 'admin')->exists()) {
        return response()->json(['message' => 'Admin already exists'], 403);
    }

    $data = $request->validate([
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users',
        'password' => 'required|min:8|confirmed',
    ]);

    $user  = User::create([
        'name'     => $data['name'],
        'email'    => $data['email'],
        'password' => bcrypt($data['password']),
        'role'     => 'admin',
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json(['user' => $user, 'token' => $token], 201);
}

public function uploadVideo(Request $request)
{
    $request->validate([
        'title'          => 'required|string|max:255',
        'video'          => 'required|file|mimetypes:video/mp4,video/avi|max:512000',
        'thumbnail'      => 'nullable|image|max:2048',
        'type'           => 'nullable|in:movie,series,episode',
        'is_premium'     => 'nullable|boolean',
        'episode_number' => 'nullable|integer',
        'series_id'      => 'nullable|exists:videos,id',
        'channel_id'     => 'required|exists:channels,id',
        'categories'       => 'nullable|array',
        'categories.*'     => 'exists:categories,id',
        'duration_seconds' => 'nullable|integer|min:1',
    ]);

    $videoPath = $request->file('video')->store('videos', 'public');
    $thumbPath = $request->hasFile('thumbnail')
        ? $request->file('thumbnail')->store('thumbnails', 'public')
        : null;

    $duration = $request->integer('duration_seconds')
        ?: VideoProbe::durationSeconds(storage_path('app/public/' . $videoPath));

    $video = Video::create([
        'channel_id'     => $request->channel_id,
        'title'          => $request->title,
        'description'    => $request->description,
        'video_path'     => $videoPath,
        'thumbnail'      => $thumbPath,
        'type'           => $request->type ?? 'movie',
        'duration'       => $duration,
        'is_premium'     => $request->boolean('is_premium'),
        'episode_number' => $request->episode_number,
        'series_id'      => $request->series_id,
        'status'         => 'published',
    ]);

    if ($request->categories) {
        $video->categories()->sync($request->categories);
    }

    return response()->json($video->load('channel', 'categories'), 201);
}
}