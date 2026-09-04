<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#42e9a6">
    <meta name="description" content="@yield('description', 'Nodexa Game Server Cloud — hurtig og moderne administration af game servers.')">
    <title>@yield('title', 'Game Server Cloud') · Nodexa</title>
    @include('partials.nodexa-theme')
    <link rel="stylesheet" href="{{ asset('css/nodexa-storefront.css') }}?v=0.14.40">
</head>
<body class="nx-storefront">
    @php
        $dedicatedStorefront = request()->routeIs('storefront.host*');
        $storefrontOrigin = rtrim(request()->getSchemeAndHttpHost(), '/');
        $panelOrigin = rtrim((string) config('app.url'), '/');
        $storefrontRoutes = [
            'home' => 'storefront.home',
            'games' => 'storefront.games',
            'pricing' => 'storefront.pricing',
            'features' => 'storefront.features',
            'support' => 'storefront.support',
        ];
        $storeUrl = static function (string $page) use ($dedicatedStorefront, $storefrontOrigin, $storefrontRoutes): string {
            if ($dedicatedStorefront) {
                return $page === 'home' ? $storefrontOrigin . '/' : $storefrontOrigin . '/' . $page;
            }

            return route($storefrontRoutes[$page]);
        };
        $panelUrl = static function (string $path = '') use ($panelOrigin): string {
            return $panelOrigin . ($path === '' ? '/' : '/' . ltrim($path, '/'));
        };
    @endphp

    <div class="nx-noise" aria-hidden="true"></div>

    <header class="nx-header" id="top">
        <div class="nx-shell nx-nav-wrap">
            <a class="nx-brand" href="{{ $storeUrl('home') }}" aria-label="Nodexa Storefront">
                <span class="nx-brand-mark">N</span>
                <span>
                    <strong>Nodexa</strong>
                    <small>GAME SERVER CLOUD</small>
                </span>
            </a>

            <button class="nx-menu-button" type="button" aria-expanded="false" aria-controls="nx-main-nav" data-menu-toggle>
                <span></span><span></span><span></span>
            </button>

            <nav class="nx-nav" id="nx-main-nav" data-menu>
                <a class="{{ request()->routeIs('storefront.home', 'storefront.host*.home') ? 'active' : '' }}" href="{{ $storeUrl('home') }}">Forside</a>
                <a class="{{ request()->routeIs('storefront.games', 'storefront.host*.games') ? 'active' : '' }}" href="{{ $storeUrl('games') }}">Game Hosting</a>
                <a class="{{ request()->routeIs('storefront.pricing', 'storefront.host*.pricing') ? 'active' : '' }}" href="{{ $storeUrl('pricing') }}">Planer</a>
                <a class="{{ request()->routeIs('storefront.features', 'storefront.host*.features') ? 'active' : '' }}" href="{{ $storeUrl('features') }}">Funktioner</a>
                <a class="{{ request()->routeIs('storefront.support', 'storefront.host*.support') ? 'active' : '' }}" href="{{ $storeUrl('support') }}">Support</a>
            </nav>

            <div class="nx-nav-actions">
                @auth
                    <a class="nx-btn nx-btn-ghost" href="{{ $panelUrl() }}">Mit panel</a>
                @else
                    <a class="nx-login" href="{{ $panelUrl('auth/login') }}">Log ind</a>
                    <a class="nx-btn nx-btn-primary" href="{{ $panelUrl('auth/login') }}">Kom i gang <span>→</span></a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="nx-footer">
        <div class="nx-shell nx-footer-grid">
            <div>
                <a class="nx-brand nx-footer-brand" href="{{ $storeUrl('home') }}">
                    <span class="nx-brand-mark">N</span>
                    <span><strong>Nodexa</strong><small>GAME SERVER CLOUD</small></span>
                </a>
                <p>Et samlet kontrolpanel til moderne game server hosting.</p>
            </div>
            <div class="nx-footer-links">
                <div>
                    <strong>Hosting</strong>
                    <a href="{{ $storeUrl('games') }}">Game Hosting</a>
                    <a href="{{ $storeUrl('pricing') }}">Planer</a>
                    <a href="{{ $storeUrl('features') }}">Funktioner</a>
                </div>
                <div>
                    <strong>Nodexa</strong>
                    <a href="{{ $storeUrl('support') }}">Support</a>
                    @auth
                        <a href="{{ $panelUrl() }}">Mit panel</a>
                    @else
                        <a href="{{ $panelUrl('auth/login') }}">Log ind</a>
                    @endauth
                </div>
            </div>
        </div>
        <div class="nx-shell nx-footer-bottom">
            <span>© {{ date('Y') }} Nodexa Software</span>
            <span>Game Server Management Platform</span>
        </div>
    </footer>

    <button class="nx-theme-trigger" type="button" data-theme-toggle aria-label="Åbn temavælger">◉ <span>Tema</span></button>
    <aside class="nx-theme-drawer" data-theme-drawer aria-hidden="true">
        <div class="nx-theme-head">
            <div><small>UDSEENDE</small><strong>Vælg Nodexa-farve</strong></div>
            <button type="button" data-theme-close aria-label="Luk">×</button>
        </div>
        <div class="nx-theme-swatches">
            <button data-accent="#42e9a6" style="--swatch:#42e9a6" aria-label="Nodexa grøn"></button>
            <button data-accent="#3b82f6" style="--swatch:#3b82f6" aria-label="Blå"></button>
            <button data-accent="#8b5cf6" style="--swatch:#8b5cf6" aria-label="Lilla"></button>
            <button data-accent="#06b6d4" style="--swatch:#06b6d4" aria-label="Cyan"></button>
            <button data-accent="#f97316" style="--swatch:#f97316" aria-label="Orange"></button>
            <button data-accent="#ec4899" style="--swatch:#ec4899" aria-label="Pink"></button>
        </div>
        <label class="nx-custom-color">Egen farve <input type="color" value="#42e9a6" data-custom-accent></label>
    </aside>
    <div class="nx-theme-backdrop" data-theme-backdrop></div>

    <script src="{{ asset('js/nodexa-storefront.js') }}?v=0.14.40" defer></script>
</body>
</html>
