<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — AMOSHWZ</title>
    <meta name="description" content="Open this video in the AMOSHWZ app.">
    <link rel="icon" type="image/svg+xml" href="{{ url('/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bebas-neue:400|manrope:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="landing">
    <main>
        <section>
            <div class="wrap" style="min-height: 80vh; display: flex; align-items: center;">
                <div class="cta-band" style="width: 100%;">
                    <p style="letter-spacing: 0.14em; text-transform: uppercase; font-size: 12px; margin-bottom: 12px;">AMOSHWZ</p>
                    <h1 class="font-display" style="font-size: clamp(36px, 7vw, 64px);">{{ $title }}</h1>
                    <p>Opening the AMOSHWZ app. If it is not installed, you will be taken to the store.</p>
                    @if ($thumbnail)
                        <img src="{{ $thumbnail }}" alt="" style="width: min(100%, 420px); border-radius: 16px; margin: 18px 0;">
                    @endif
                    <div class="cta-actions">
                        <a class="btn btn-brand" id="open-app" href="{{ $appUri }}">Open in app</a>
                        <a class="btn btn-light" id="store-link" href="{{ $playStoreUrl }}">Get the app</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
        (function () {
            var appUri = @json($appUri);
            var playStore = @json($playStoreUrl);
            var appStore = @json($appStoreUrl);
            var isIos = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            var store = isIos ? appStore : playStore;
            var storeLink = document.getElementById('store-link');
            if (storeLink) storeLink.href = store;

            var leftPage = false;
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) leftPage = true;
            });
            window.addEventListener('pagehide', function () { leftPage = true; });

            window.location.href = appUri;
            setTimeout(function () {
                if (!leftPage && !document.hidden) {
                    window.location.href = store;
                }
            }, 1600);
        })();
    </script>
</body>
</html>
