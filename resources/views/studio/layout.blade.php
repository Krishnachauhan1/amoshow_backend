<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Studio') — AMOSHWZ</title>
    <link rel="icon" type="image/svg+xml" href="{{ url('/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bebas-neue:400|manrope:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/studio.css') }}">
</head>
<body class="landing studio">
    <header class="site-header is-scrolled studio-header">
        <div class="wrap header-inner">
            <a href="{{ url('/') }}" class="brand">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                </span>
                <span class="brand-name font-display">AMSHOW</span>
                <span class="studio-badge">Studio</span>
            </a>
            @auth
                <nav class="nav-links studio-nav">
                    <a href="{{ route('studio.index') }}" class="{{ request()->routeIs('studio.index') ? 'is-active' : '' }}">Uploads</a>
                    <a href="{{ route('studio.upload') }}" class="{{ request()->routeIs('studio.upload') ? 'is-active' : '' }}">Upload</a>
                </nav>
                <div class="header-actions studio-nav">
                    <span class="studio-user">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('studio.logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="padding: 8px 16px;">Log out</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>
    <main class="studio-main">
        <div class="wrap">
            @if (session('status'))
                <div class="studio-flash">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="studio-flash is-error">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </main>
    @yield('scripts')
</body>
</html>
