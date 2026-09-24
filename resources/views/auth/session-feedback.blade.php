<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $productName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background font-sans text-text antialiased">
    <main
        class="auth-feedback flex min-h-screen items-center justify-center px-4 py-10"
        data-auth-feedback
        data-mode="{{ $mode }}"
        data-redirect-url="{{ $redirectUrl }}"
        data-duration-ms="{{ $durationMs }}"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <div class="auth-feedback__card w-full max-w-md rounded-3xl border border-border bg-surface px-8 py-12 text-center shadow-[0_24px_64px_-28px_rgba(16,35,58,0.28)] sm:px-10">
            <div class="auth-feedback__mark relative mx-auto flex size-20 items-center justify-center rounded-full bg-primary/8 ring-1 ring-primary/15" aria-hidden="true">
                <svg class="auth-feedback__ring size-20 text-accent" viewBox="0 0 80 80" fill="none">
                    <circle class="auth-feedback__circle" cx="40" cy="40" r="34" stroke="currentColor" stroke-width="2.5" />
                </svg>
                <svg class="auth-feedback__check absolute size-9 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path class="auth-feedback__check-path" d="M5.5 12.5 10 17l8.5-9.5" />
                </svg>
            </div>

            @if ($greeting)
                <p class="auth-feedback__title mt-7 text-xs font-semibold uppercase tracking-[0.18em] text-accent">
                    {{ $title }}
                </p>
                <h1 class="auth-feedback__greeting mt-3 text-2xl font-semibold tracking-tight text-text sm:text-[1.7rem]">
                    {{ $greeting }}
                </h1>
            @else
                <h1 class="auth-feedback__greeting mt-7 text-2xl font-semibold tracking-tight text-text sm:text-[1.7rem]">
                    {{ $title }}
                </h1>
            @endif

            <p class="auth-feedback__subtitle mt-3 text-sm leading-6 text-text-muted">
                {{ $subtitle }}
            </p>

            <div class="auth-feedback__progress mx-auto mt-8 h-1 max-w-[11rem] overflow-hidden rounded-full bg-border" aria-hidden="true">
                <div class="auth-feedback__progress-bar h-full rounded-full bg-accent"></div>
            </div>
        </div>
    </main>

    <script>
        (function () {
            var root = document.querySelector('[data-auth-feedback]');
            if (!root) {
                return;
            }

            var url = root.getAttribute('data-redirect-url');
            var duration = parseInt(root.getAttribute('data-duration-ms') || '1200', 10);
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduced) {
                root.classList.add('auth-feedback--reduced');
                duration = Math.min(duration, 320);
            } else {
                requestAnimationFrame(function () {
                    root.classList.add('auth-feedback--ready');
                });
            }

            window.setTimeout(function () {
                if (url) {
                    window.location.replace(url);
                }
            }, duration);
        })();
    </script>
</body>
</html>
