<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StudioAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('studio.login', ['tab' => 'login']);
    }

    public function showRegister(): View
    {
        return view('studio.login', ['tab' => 'register']);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $request->session()->regenerate();
        $this->ensureChannel(Auth::user());

        return redirect()->intended(route('studio.index'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'referral_code' => 'nullable|string|max:40',
        ]);

        $referralCode = $data['referral_code'] ?? $request->cookie('amoshwz_ref');
        unset($data['referral_code']);

        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        try {
            Referral::attach($user, $referralCode);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->ensureChannel($user);

        return redirect()->route('studio.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('studio.login');
    }

    private function ensureChannel(?User $user): void
    {
        if (! $user) {
            return;
        }

        try {
            if (! $user->channel) {
                $user->channel()->create([
                    'name' => ($user->name ?: 'My').' Channel',
                ]);
            }
        } catch (\Throwable) {
            // Upload can still create the channel later.
        }
    }
}
