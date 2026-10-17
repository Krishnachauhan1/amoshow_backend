<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $user  = User::create($data);
        $token = $user->createToken('auth_token')->plainTextToken;

        try {
            $user->channel()->create([
                'name' => $data['name'].' Channel',
            ]);
        } catch (\Throwable) {
            // Channel can still be created later from the app.
        }

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        try {
            if (! $user->channel) {
                $user->channel()->create([
                    'name' => ($user->name ?: 'My').' Channel',
                ]);
            }
        } catch (\Throwable) {
            // Upload flow can still create the channel later.
        }

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json(
            $this->formatUserResponse($request->user())
        );
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $data = $request->validate([
                'name'   => 'sometimes|required|string|max:255',
                'email'  => 'sometimes|required|email|unique:users,email,' . $user->id,
                'avatar' => 'nullable|file|max:5120',
            ]);

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $ext = $this->detectImageExtension($file);
                if ($ext === null) {
                    return response()->json([
                        'message' => 'Avatar must be a jpg, jpeg, png, or webp image.',
                        'errors' => ['avatar' => ['The avatar must be a jpg, jpeg, png, or webp image.']],
                    ], 422);
                }
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $data['avatar'] = $file->storeAs(
                    'avatars',
                    uniqid('avatar_', true).'.'.$ext,
                    'public'
                );
            }

            unset($data['avatar_file']);

            if (! empty($data)) {
                $user->update($data);
            }

            return response()->json([
                'message' => 'Profile updated successfully',
                'user'    => $this->formatUserResponse($user->fresh()),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => app()->isProduction()
                    ? 'Could not update profile photo. Check storage permissions on server.'
                    : $e->getMessage(),
            ], 500);
        }
    }

    private function detectImageExtension(\Illuminate\Http\UploadedFile $file): ?string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = bin2hex((string) fread($handle, 16));
        fclose($handle);

        return match (true) {
            str_starts_with($head, 'ffd8ff') => 'jpg',
            str_starts_with($head, '89504e47') => 'png',
            str_contains($head, '57454250') => 'webp',
            str_starts_with($head, '47494638') => 'gif',
            default => null,
        };
    }

    private function formatUserResponse(User $user): array
    {
        try {
            $user->loadMissing(['channel', 'subscriptions']);
        } catch (\Throwable $e) {
            report($e);
            $user->loadMissing(['channel']);
        }

        $payload = $user->toArray();
        $payload['avatar_url'] = \App\Support\YoutubeFormatter::storageUrl($user->avatar);

        return $payload;
    }
}