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
                'avatar' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
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