<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #faf8f5;
            }

            html.dark {
                background-color: #0b0f14;
            }
        </style>

        @isset($meta)
            <meta name="description" content="{{ $meta['description'] }}">
            <meta property="og:type" content="website">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta property="og:url" content="{{ $meta['url'] }}">
            @if ($meta['image'])
                <meta property="og:image" content="{{ $meta['image'] }}">
            @endif
            <link rel="canonical" href="{{ $meta['url'] }}">
        @endisset

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ $appIcon ?? '/apple-touch-icon.png' }}">
        {{-- Installable app: a salon's page installs with that salon's name and logo. --}}
        <link rel="manifest" href="{{ $manifest ?? route('manifest', absolute: false) }}">
        <meta name="theme-color" content="#faf8f5" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b0f14" media="(prefers-color-scheme: dark)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ $appTitle ?? config('app.name') }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
