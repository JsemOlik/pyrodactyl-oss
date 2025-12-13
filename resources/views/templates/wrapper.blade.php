<!DOCTYPE html>
<!-- Copyright (c) 2025-2025 Quark Inc., 2023-2025 Pyro Inc., parent collaborators, and contributors -->
<html data-pyro-html lang="en" style="background-color: #000000; height: 100%; width: 100%; margin: 0; padding: 0;">

<head>
    <title>{{ config('app.name', 'Panel') }}</title>

    @section('meta')
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">

    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Pyrodactyl" />
    <link rel="manifest" href="/favicons/site.webmanifest" />

    <meta name="theme-color" content="#000000">
    <meta name="darkreader-lock">
    @show

    @section('user-data')
    @if(!is_null(Auth::user()))
        <script>
            window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
        </script>
    @endif
    @if(!empty($siteConfiguration))
        <script>
            window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
        </script>
    @endif
    @show
    <style>
        @import url('https://fonts.bunny.net/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap')
    </style>

    @yield('assets')

    @include('layouts.scripts')

    @viteReactRefresh
    @vite('resources/scripts/index.tsx')

    <script>
        (function() {
            const primaryColor = '{{ config('theme.primary_color', '#fa4e49') }}';
            const buttonBorderRadius = '{{ config('theme.button_border_radius', '0.5rem') }}';
            // Store border radius in data attribute for hosting page to use
            document.documentElement.setAttribute('data-hosting-button-radius', buttonBorderRadius);
            function applyThemeColor() {
                const root = document.documentElement;
                root.style.setProperty('--color-brand', primaryColor);
                // Don't set border radius globally - only for hosting page
                // root.style.setProperty('--button-border-radius', buttonBorderRadius);
                // Update brand gradient to use the new color
                const rgb = hexToRgb(primaryColor);
                if (rgb) {
                    const lighter = adjustBrightness(primaryColor, 1.2);
                    root.style.setProperty('--color-brand-grad', `radial-gradient(109.26% 109.26% at 49.83% 13.37%, ${primaryColor} 0%, ${lighter} 100%)`);
                }
            }
            function hexToRgb(hex) {
                const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                return result ? {
                    r: parseInt(result[1], 16),
                    g: parseInt(result[2], 16),
                    b: parseInt(result[3], 16)
                } : null;
            }
            function adjustBrightness(hex, factor) {
                const rgb = hexToRgb(hex);
                if (!rgb) return hex;
                const r = Math.min(255, Math.round(rgb.r * factor));
                const g = Math.min(255, Math.round(rgb.g * factor));
                const b = Math.min(255, Math.round(rgb.b * factor));
                return '#' + [r, g, b].map(x => {
                    const hex = x.toString(16);
                    return hex.length === 1 ? '0' + hex : hex;
                }).join('');
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', applyThemeColor);
            } else {
                applyThemeColor();
            }
            // Also apply after a short delay to ensure Vite bundle has loaded
            setTimeout(applyThemeColor, 100);
        })();
    </script>
</head>

<body data-pyro-body class="{{ $css['body'] }}"
    style="background-color: #000000; height: 100%; width: 100%; margin: 0; padding: 0;">
    @section('content')
    @yield('above-container')
    @yield('container')
    @yield('below-container')
    @show
</body>

</html>