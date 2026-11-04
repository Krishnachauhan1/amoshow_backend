@extends('studio.layout')

@section('title', ($tab ?? 'login') === 'register' ? 'Create account' : 'Log in')

@section('content')
    <div class="studio-auth">
        <p class="studio-kicker">Creator studio</p>
        <h1 class="font-display studio-title">{{ ($tab ?? 'login') === 'register' ? 'Create your account' : 'Log in to upload' }}</h1>
        <p class="studio-copy">Use the same AMOSHWZ account as the app.</p>

        <div class="studio-tabs">
            <a href="{{ route('studio.login') }}" class="{{ ($tab ?? 'login') === 'login' ? 'is-active' : '' }}">Log in</a>
            <a href="{{ route('studio.register') }}" class="{{ ($tab ?? 'login') === 'register' ? 'is-active' : '' }}">Create account</a>
        </div>

        @if (($tab ?? 'login') === 'register')
            <form method="POST" action="{{ url()->current() }}" class="studio-card studio-form">
                @csrf
                <label>
                    Full name
                    <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                </label>
                <label>
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </label>
                <label>
                    Password
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </label>
                <label>
                    Confirm password
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </label>
                <label>
                    Referral code <span>(optional)</span>
                    <input type="text" name="referral_code" value="{{ old('referral_code') }}" maxlength="40" autocomplete="off">
                </label>
                <button type="submit" class="btn btn-brand btn-block">Create account</button>
            </form>
        @else
            <form method="POST" action="{{ url()->current() }}" class="studio-card studio-form">
                @csrf
                <label>
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </label>
                <label>
                    Password
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <label class="studio-check">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
                <button type="submit" class="btn btn-brand btn-block">Log in</button>
            </form>
        @endif
    </div>
@endsection
