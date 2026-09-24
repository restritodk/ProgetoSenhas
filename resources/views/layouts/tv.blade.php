<!doctype html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#1e3a5f">
    <title>@yield('title', 'Painel') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        :root {
            --tv-header-h: clamp(4.25rem, 12vh, 7.5rem);
            --tv-footer-h: clamp(3rem, 10vh, 5.75rem);
            --tv-gap: clamp(0.4rem, 0.9vw, 1rem);
            --tv-pad: clamp(0.4rem, 1vw, 1rem);
            --tv-code: clamp(3.75rem, 14.5vmin, 11.75rem);
            --tv-desk: clamp(1.35rem, 5vmin, 3.35rem);
            --tv-history: clamp(0.95rem, 2.4vmin, 1.65rem);
            --tv-time: clamp(1.35rem, 4vmin, 2.75rem);
            --tv-logo-h: clamp(3rem, 9vh, 5.5rem);
            --tv-logo-w: clamp(12rem, 26vw, 26rem);
            --tv-safe-t: env(safe-area-inset-top, 0px);
            --tv-safe-r: env(safe-area-inset-right, 0px);
            --tv-safe-b: env(safe-area-inset-bottom, 0px);
            --tv-safe-l: env(safe-area-inset-left, 0px);
        }

        .tv-shell {
            height: 100vh;
            height: 100dvh;
            max-height: 100vh;
            max-height: 100dvh;
            padding:
                var(--tv-safe-t)
                var(--tv-safe-r)
                var(--tv-safe-b)
                var(--tv-safe-l);
        }

        .tv-header {
            height: var(--tv-header-h);
            min-height: var(--tv-header-h);
            max-height: var(--tv-header-h);
        }

        .tv-footer {
            height: var(--tv-footer-h);
            min-height: var(--tv-footer-h);
            max-height: var(--tv-footer-h);
        }

        .tv-code {
            font-size: var(--tv-code);
            line-height: 0.92;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tv-desk {
            font-size: var(--tv-desk);
            line-height: 1.1;
            max-width: 100%;
        }

        .tv-history-code {
            font-size: var(--tv-history);
        }

        .tv-time {
            font-size: var(--tv-time);
        }

        /* Tablets e notebooks em paisagem / TVs menores */
        @media (min-width: 768px) and (min-height: 560px) {
            :root {
                --tv-code: clamp(4.4rem, 13.5vmin, 11.75rem);
                --tv-desk: clamp(1.6rem, 4.5vmin, 3.35rem);
            }
        }

        /* Full HD e superiores */
        @media (min-width: 1600px) and (min-height: 900px) {
            :root {
                --tv-header-h: clamp(5.5rem, 12vh, 8rem);
                --tv-footer-h: clamp(4.25rem, 11vh, 6.25rem);
                --tv-code: clamp(6.75rem, 12.8vmin, 13.5rem);
                --tv-desk: clamp(2.05rem, 4.25vmin, 3.85rem);
                --tv-history: clamp(1.15rem, 2.2vmin, 1.85rem);
                --tv-time: clamp(2rem, 3.8vmin, 3.1rem);
                --tv-logo-h: clamp(4rem, 9vh, 5.75rem);
                --tv-logo-w: clamp(20rem, 22vw, 26rem);
            }
        }

        .tv-brand-logo {
            display: block;
            width: auto;
            height: auto;
            max-width: min(100%, var(--tv-logo-w));
            max-height: min(calc(var(--tv-header-h) - 0.75rem), var(--tv-logo-h));
            object-fit: contain;
            object-position: left center;
            background: transparent;
        }

        .tv-brand-logo-wrap {
            display: inline-flex;
            max-width: 100%;
            align-items: center;
            justify-content: flex-start;
            line-height: 0;
        }

        .tv-brand-logo-wrap--transparent {
            background: transparent;
            padding: 0;
            border-radius: 0;
        }

        .tv-brand-logo-wrap--light,
        .tv-brand-logo-wrap--dark,
        .tv-brand-logo-wrap--custom {
            border-radius: 0.85rem;
            padding: 0.3rem 0.9rem;
        }

        .tv-brand-logo-wrap--light {
            background: rgba(255, 255, 255, 0.94);
        }

        .tv-brand-logo-wrap--dark {
            background: rgba(8, 18, 32, 0.72);
        }

        .tv-brand-logo-wrap--custom {
            background: #ffffff;
        }

        .tv-brand-logo-wrap--light .tv-brand-logo,
        .tv-brand-logo-wrap--dark .tv-brand-logo,
        .tv-brand-logo-wrap--custom .tv-brand-logo {
            max-height: min(calc(var(--tv-header-h) - 1.35rem), var(--tv-logo-h));
        }

        /* 4K / painéis muito grandes */
        @media (min-width: 2560px) {
            :root {
                --tv-code: clamp(9.5rem, 11.5vmin, 16rem);
                --tv-desk: clamp(2.65rem, 3.8vmin, 4.75rem);
                --tv-history: clamp(1.35rem, 2vmin, 2.25rem);
                --tv-time: clamp(2.5rem, 3.2vmin, 3.75rem);
            }
        }

        /* Celular retrato: senha em destaque, mídia secundária */
        @media (max-width: 767px) and (orientation: portrait) {
            :root {
                --tv-header-h: clamp(4rem, 11vh, 5.5rem);
                --tv-footer-h: clamp(2.75rem, 8.5vh, 4rem);
                --tv-code: clamp(3.5rem, 16vw, 6.75rem);
                --tv-desk: clamp(1.35rem, 6vw, 2.35rem);
                --tv-logo-h: clamp(2.25rem, 7vh, 3.5rem);
                --tv-logo-w: clamp(9rem, 48vw, 16rem);
            }
        }

        /* Celular/tablet baixo ou paisagem curta */
        @media (max-height: 520px) {
            :root {
                --tv-header-h: 3.5rem;
                --tv-footer-h: 2.5rem;
                --tv-code: clamp(2.5rem, 18vh, 5rem);
                --tv-desk: clamp(1.15rem, 7vh, 1.9rem);
                --tv-history: clamp(0.8rem, 4.5vh, 1.15rem);
                --tv-time: clamp(1.1rem, 7vh, 1.75rem);
                --tv-logo-h: 2rem;
                --tv-logo-w: 10rem;
                --tv-gap: 0.35rem;
                --tv-pad: 0.35rem;
            }
        }

        @keyframes tv-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.35); }
            50% { box-shadow: 0 0 0 14px rgba(220, 38, 38, 0); }
        }
        .tv-highlight {
            animation: tv-pulse 1.05s ease-in-out 2;
        }
        .tv-code-pulse {
            animation: tv-code-beat 1.05s ease-in-out 2;
        }
        @keyframes tv-code-beat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.03); }
        }
        @media (prefers-reduced-motion: reduce) {
            .tv-highlight,
            .tv-code-pulse { animation: none; }
        }
    </style>
</head>
<body class="h-full overflow-hidden bg-[#dbe5f0] text-text antialiased">
    @yield('content')
    @livewireScripts
</body>
</html>
