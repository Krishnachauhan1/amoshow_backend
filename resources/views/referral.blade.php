<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join AMOSHWZ{{ $valid ? ' with '.$referrerName : '' }}</title>
    <meta name="description" content="Sign up on AMOSHWZ with referral code {{ $code }}.">
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
                    <p style="letter-spacing: 0.14em; text-transform: uppercase; font-size: 12px; margin-bottom: 12px;">Referral invite</p>
                    <h1 class="font-display" style="font-size: clamp(42px, 8vw, 72px);">
                        {{ $valid ? $referrerName.' invited you' : 'Join AMOSHWZ' }}
                    </h1>
                    <p>
                        @if ($valid)
                            Opening the AMOSHWZ app so you can sign up with this code. {{ $referrerName }} will get credit.
                        @else
                            This invite link is missing a valid code. You can still download AMOSHWZ and sign up.
                        @endif
                    </p>
                    <div style="margin: 22px 0; padding: 16px 18px; border: 1px solid rgba(255,255,255,0.16); border-radius: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <div style="font-size: 12px; opacity: 0.7;">Your referral code</div>
                            <div class="font-display" style="font-size: 32px; letter-spacing: 0.08em;">{{ $code }}</div>
                        </div>
                        <button type="button" class="btn btn-light" data-copy-code="{{ $code }}">Copy code</button>
                    </div>
                    <div class="cta-actions">
                        <a class="btn btn-brand" id="open-app" href="{{ $appUri }}">Open app and sign up</a>
                        <a class="btn btn-ghost" id="store-link" href="{{ $playStoreUrl }}">Get the app</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-copy-code]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var code = btn.getAttribute('data-copy-code') || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code);
                }
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy code'; }, 1600);
            });
        });

        (function () {
            var appUri = @json($appUri);
            var playStore = @json($playStoreUrl);
            var appStore = @json($appStoreUrl);
            var code = @json($code);
            var isIos = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            var isAndroid = /Android/i.test(navigator.userAgent);
            var store = isIos ? appStore : playStore;
            var storeLink = document.getElementById('store-link');
            if (storeLink) storeLink.href = store;

            var intentUri = 'intent://ref/' + encodeURIComponent(code) +
                '#Intent;scheme=amoshwz;package=com.amoshwz;S.browser_fallback_url=' +
                encodeURIComponent(window.location.href) + ';end';
            var openHref = isAndroid ? intentUri : appUri;
            var openBtn = document.getElementById('open-app');
            if (openBtn) openBtn.href = openHref;

            var leftPage = false;
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) leftPage = true;
            });
            window.addEventListener('pagehide', function () { leftPage = true; });

            window.location.href = openHref;
        })();
    </script>
</body>
</html>
