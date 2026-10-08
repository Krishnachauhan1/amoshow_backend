<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AMShow — Movies, Shorts, Music &amp; Live Culture</title>
    <meta name="description" content="AMShow is the all-in-one stage for movies, series, shorts, music, games, and creator channels. Watch, create, and subscribe in one place.">
    <link rel="icon" type="image/svg+xml" href="{{ url('/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bebas-neue:400|manrope:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="landing">
    @php
        $fallbackTitles = [
            ['title' => 'Night Circuit', 'meta' => 'Action · 2h 14m', 'tone' => '#c81e4a, #f5c14a'],
            ['title' => 'The Last Broadcast', 'meta' => 'Thriller · 1h 48m', 'tone' => '#4f46e5, #d946ef'],
            ['title' => 'Amber Hour', 'meta' => 'Drama · 1h 56m', 'tone' => '#f59e0b, #c2410c'],
            ['title' => 'River Kingdom', 'meta' => 'Series · 8 episodes', 'tone' => '#14b8a6, #065f46'],
            ['title' => 'Afterlight', 'meta' => 'Sci-Fi · 2h 03m', 'tone' => '#7c3aed, #1e293b'],
            ['title' => 'Echoes of June', 'meta' => 'Romance · 1h 41m', 'tone' => '#ec4899, #9f1239'],
            ['title' => 'Glass City', 'meta' => 'Crime · 2h 21m', 'tone' => '#0284c7, #0f172a'],
            ['title' => 'Northern Signal', 'meta' => 'Documentary · 54m', 'tone' => '#06b6d4, #1e3a8a'],
        ];
        $catalog = $featured->isNotEmpty()
            ? $featured
            : collect($fallbackTitles)->map(fn ($item) => [
                'title' => $item['title'],
                'thumbnail' => null,
                'duration' => null,
                'channel' => $item['meta'],
                'premium' => false,
                'tone' => $item['tone'],
            ]);
        $shortTones = ['#ff3b6b, #f5c14a', '#d946ef, #4f46e5', '#34d399, #0e7490', '#fb923c, #e11d48', '#f5c14a, #44403c', '#38bdf8, #6d28d9'];
        $shortCards = $shorts->isNotEmpty()
            ? $shorts
            : collect(['60-second heat', 'Street beats', 'Chef mode', 'Night ride', 'Studio cut', 'Game drop'])->map(fn ($title, $i) => [
                'title' => $title,
                'thumbnail' => null,
                'channel' => 'AMShow Originals',
                'tone' => $shortTones[$i],
            ]);
        $planCards = $plans->isNotEmpty()
            ? $plans
            : collect([
                (object) ['name' => 'Free', 'price' => 0, 'duration_days' => 0, 'features' => ['Public videos & shorts', 'Follow channels', 'Watch history']],
                (object) ['name' => 'Premium', 'price' => 199, 'duration_days' => 30, 'features' => ['All movies & series', 'Ad-light playback', 'Downloads & continue watching', 'Exclusive premieres']],
                (object) ['name' => 'Creator', 'price' => 499, 'duration_days' => 30, 'features' => ['Everything in Premium', 'Channel studio tools', 'Collab invites', 'Monetization insights']],
            ]);
        $watchUrl = 'https://amoshow.nextlogicsolution.id';
    @endphp

    <header class="site-header" data-landing-nav>
        <div class="wrap header-inner">
            <a href="#top" class="brand">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                </span>
                <span class="brand-name font-display">AMSHOW</span>
            </a>
            <nav class="nav-links">
                <a href="#catalog">Watch</a>
                <a href="#features">Features</a>
                <a href="#creators">Creators</a>
                <a href="#plans">Plans</a>
            </nav>
            <div class="header-actions">
                <a href="{{ $watchUrl }}">Open app</a>
                <a class="btn btn-brand" href="#plans" style="padding: 8px 20px;">Get Premium</a>
            </div>
            <button type="button" class="menu-btn" data-menu-button aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>
        </div>
        <div class="wrap">
            <div id="mobile-menu" class="mobile-menu" data-mobile-menu>
                <a href="#catalog">Watch</a>
                <a href="#features">Features</a>
                <a href="#creators">Creators</a>
                <a href="#plans">Plans</a>
                <a href="{{ $watchUrl }}">Open app</a>
            </div>
        </div>
    </header>

    <main id="top">
        <section class="hero">
            <div class="hero-glow"></div>
            <div class="wrap hero-grid">
                <div>
                    <p class="eyebrow">Movies · Shorts · Music · Channels</p>
                    <h1 class="font-display">All of entertainment.<br><span>One show.</span></h1>
                    <p class="lede">AMShow is the stage for movies, series, creator videos, shorts, music, games, and news — with premium catalogs, pay-per-title drops, and a studio for people who make the culture.</p>
                    <div class="hero-actions">
                        <a class="btn btn-light" href="{{ $watchUrl }}">
                            <svg viewBox="0 0 24 24"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                            Start watching
                        </a>
                        <a class="btn btn-ghost" href="#creators">Create on AMShow</a>
                    </div>
                    <dl class="stats">
                        <div>
                            <dt>Catalogs</dt>
                            <dd>OTT + Shorts</dd>
                        </div>
                        <div>
                            <dt>For creators</dt>
                            <dd>Upload &amp; earn</dd>
                        </div>
                        <div>
                            <dt>Membership</dt>
                            <dd>From ₹0</dd>
                        </div>
                    </dl>
                </div>
                <div class="hero-visual">
                    <div class="device">
                        <div class="device-screen">
                            <span class="live-pill">Now streaming</span>
                            <h2 class="font-display">Prime night</h2>
                            <p>Movies, series, and original drops in one feed.</p>
                        </div>
                        <div class="device-tabs">
                            <span>Movies</span>
                            <span>Shorts</span>
                            <span>Music</span>
                        </div>
                    </div>
                    <aside class="float-card watch">
                        <p class="muted" style="margin:0;font-size:11px;letter-spacing:.08em;text-transform:uppercase;">Continue watching</p>
                        <p style="margin:4px 0 0;font-size:14px;font-weight:700;">Episode 4 · 18:22</p>
                        <div class="progress"><span></span></div>
                    </aside>
                    <aside class="float-card short">New short · 0:28</aside>
                </div>
            </div>
        </section>

        <div class="marquee">
            <div class="marquee-track">
                @foreach ([1, 2] as $pass)
                    <span>Movies</span><span class="dot">●</span>
                    <span>TV Series</span><span class="dot">●</span>
                    <span>Shorts</span><span class="dot">●</span>
                    <span>Music</span><span class="dot">●</span>
                    <span>Games</span><span class="dot">●</span>
                    <span>News</span><span class="dot">●</span>
                    <span>Channels</span><span class="dot">●</span>
                    <span>Premium</span><span class="dot">●</span>
                @endforeach
            </div>
        </div>

        <section id="catalog">
            <div class="wrap">
                <div class="section-head">
                    <div>
                        <p class="kicker">Catalog</p>
                        <h2 class="font-display">Tonight’s lineup</h2>
                    </div>
                    <a class="link-quiet" href="{{ $watchUrl }}">Browse all →</a>
                </div>
                <div class="rail">
                    @foreach ($catalog as $item)
                        <article class="poster">
                            <div class="poster-art">
                                @if (!empty($item['thumbnail']))
                                    <img src="{{ $item['thumbnail'] }}" alt="{{ $item['title'] }}">
                                @else
                                    <div class="poster-fallback" style="background-color:#1a1a24; background-image:linear-gradient(160deg, rgba(255,255,255,.08), transparent 42%), linear-gradient(180deg, transparent 40%, rgba(0,0,0,.78)), linear-gradient(135deg, {{ $item['tone'] ?? '#3f3f46, #09090b' }});"></div>
                                @endif
                                @if (!empty($item['premium']))
                                    <span class="badge">Premium</span>
                                @endif
                                @if (!empty($item['duration']) && $item['duration'] !== '0:00')
                                    <span class="duration">{{ $item['duration'] }}</span>
                                @endif
                            </div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['channel'] ?? 'Title' }}</p>
                        </article>
                    @endforeach
                </div>

                <div class="shorts-block">
                    <h3 class="font-display section-title">Shorts, made to binge</h3>
                    <div class="rail">
                        @foreach ($shortCards as $item)
                            <article class="short-card">
                                <div class="short-art">
                                    @if (!empty($item['thumbnail']))
                                        <img src="{{ $item['thumbnail'] }}" alt="{{ $item['title'] }}">
                                    @else
                                        <div class="short-fallback" style="background-image:linear-gradient(160deg, rgba(255,255,255,.08), transparent 42%), linear-gradient(180deg, transparent 35%, rgba(0,0,0,.8)), linear-gradient(160deg, {{ $item['tone'] ?? '#ff3b6b, #09090b' }});"></div>
                                    @endif
                                    <div class="short-copy">
                                        <h3 style="margin:0;font-size:14px;">{{ $item['title'] }}</h3>
                                        <p>{{ $item['channel'] ?? 'Shorts' }}</p>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="band">
            <div class="wrap">
                <p class="kicker gold">Why AMShow</p>
                <h2 class="font-display">A full entertainment OS, not just a player.</h2>
                <div class="feature-grid" style="margin-top:48px;">
                    @foreach ([
                        ['title' => 'Movies & series', 'body' => 'OTT catalogs with categories, episodes, continue watching, and range-ready streaming.'],
                        ['title' => 'Creator channels', 'body' => 'YouTube-style feeds, likes, comments, subscriptions, and channel privacy controls.'],
                        ['title' => 'Vertical shorts', 'body' => 'A dedicated shorts rail with likes, comments, genres, and trending discovery.'],
                        ['title' => 'Music, games & news', 'body' => 'Home sections for music, top 10s, games, products, and headlines — curated by your team.'],
                        ['title' => 'Premium & pay-per-title', 'body' => 'Membership plans plus one-off video purchases when a title should stand on its own.'],
                        ['title' => 'Studio for makers', 'body' => 'Chunked uploads, collab invites, ads, earnings, and notifications built into the same account.'],
                    ] as $feature)
                        <article class="feature-card">
                            <h3>{{ $feature['title'] }}</h3>
                            <p>{{ $feature['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="creators">
            <div class="wrap split">
                <div class="creator-copy">
                    <p class="kicker">Creators</p>
                    <h2 class="font-display">Build a channel. Invite collabs. Get paid.</h2>
                    <p>Open a channel, upload long-form or shorts, drop paid premieres, and bring other creators onto a title. AMShow keeps watch history, comments, and earnings in one studio.</p>
                    <ul>
                        <li>HD uploads with chunked transfer for large files</li>
                        <li>Collaboration requests, notifications, and approvals</li>
                        <li>Memberships, ads, and per-video pricing</li>
                    </ul>
                </div>
                <div class="channel-grid">
                    @forelse ($channels as $channel)
                        <article class="channel-card">
                            <div class="avatar">
                                @if (!empty($channel['avatar']))
                                    <img src="{{ $channel['avatar'] }}" alt="">
                                @else
                                    {{ strtoupper(substr($channel['name'], 0, 1)) }}
                                @endif
                            </div>
                            <h3>{{ $channel['name'] }}</h3>
                            <p>{{ $channel['subscribers'] }} subscribers</p>
                        </article>
                    @empty
                        @foreach (['Studio North', 'Red Room Films', 'Midnight Radio', 'Arcade India'] as $name)
                            <article class="channel-card">
                                <div class="avatar gradient">{{ strtoupper(substr($name, 0, 1)) }}</div>
                                <h3>{{ $name }}</h3>
                                <p>Featured channel</p>
                            </article>
                        @endforeach
                    @endforelse
                </div>
            </div>
        </section>

        <section id="plans" class="band">
            <div class="wrap plans">
                <p class="kicker gold">Membership</p>
                <h2 class="font-display">Watch free. Unlock everything.</h2>
                <p class="lede">Start on the public catalog, then subscribe when you want premium movies, series, and creator perks.</p>
                <div class="plan-grid" style="margin-top:48px;">
                    @foreach ($planCards as $index => $plan)
                        @php
                            $featuredPlan = $index === min(1, $planCards->count() - 1);
                            $features = is_array($plan->features ?? null) ? $plan->features : [];
                        @endphp
                        <article class="plan-card {{ $featuredPlan ? 'featured' : '' }}">
                            @if ($featuredPlan)
                                <span class="popular">Popular</span>
                            @endif
                            <h3>{{ $plan->name }}</h3>
                            <p class="price font-display">{{ $plan->price > 0 ? '₹'.number_format($plan->price, 0) : '₹0' }}</p>
                            <p>
                                @if ($plan->price > 0 && $plan->duration_days)
                                    every {{ $plan->duration_days }} days
                                @else
                                    forever
                                @endif
                            </p>
                            <ul>
                                @forelse ($features as $feature)
                                    <li>{{ $feature }}</li>
                                @empty
                                    <li>Access AMShow membership benefits</li>
                                @endforelse
                            </ul>
                            <a class="btn btn-block {{ $featuredPlan ? 'btn-brand' : 'btn-light' }}" href="{{ $watchUrl }}">
                                {{ $plan->price > 0 ? 'Choose '.$plan->name : 'Start free' }}
                            </a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section>
            <div class="wrap">
                <div class="cta-band">
                    <h2 class="font-display">The show is on.</h2>
                    <p>Open AMShow for movies, shorts, music, and the creators behind them — or start a channel and put your work on the same stage.</p>
                    <div class="cta-actions">
                        <a class="btn btn-light" href="{{ $watchUrl }}">Watch now</a>
                        <a class="btn btn-ghost" href="#creators">Become a creator</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="wrap footer-inner">
            <div class="brand">
                <span class="footer-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                </span>
                <span class="brand-name font-display">AMSHOW</span>
            </div>
            <div class="footer-links">
                <a href="{{ url('/privacy.html') }}">Privacy Policy</a>
                <a href="{{ url('/delete-account.html') }}">Delete Account</a>
            </div>
            <p>© {{ date('Y') }} AMShow. Movies, shorts, music, and makers — in one place.</p>
        </div>
    </footer>

    <script src="{{ asset('js/landing.js') }}"></script>
</body>
</html>
