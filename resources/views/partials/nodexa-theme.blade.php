@php
    $nodexaThemeState = app(\Pterodactyl\Services\Nodexa\ThemeService::class)->current();
    $nodexaThemeColors = $nodexaThemeState['colors'];
@endphp
<link rel="stylesheet" href="{{ asset('css/nodexa-themes.css') }}?v=1">
<style>
:root {
    --blue: {{ $nodexaThemeColors['accent'] }};
    --purple: {{ $nodexaThemeColors['secondary'] }};
    --green: {{ $nodexaThemeColors['success'] }};
    --bg: {{ $nodexaThemeColors['bg'] }};
    --surface: {{ $nodexaThemeColors['surface'] }};
    --card: {{ $nodexaThemeColors['card'] }};
    --card2: {{ $nodexaThemeColors['card'] }};
    --line: {{ $nodexaThemeColors['border'] }};
    --line2: {{ $nodexaThemeColors['adminBorder'] }};
    --nx-bg: {{ $nodexaThemeColors['adminBg'] }};
    --nx-panel: {{ $nodexaThemeColors['adminCard'] }};
    --nx-panel2: {{ $nodexaThemeColors['card'] }};
    --nx-border: {{ $nodexaThemeColors['adminBorder'] }};
    --nx-accent: {{ $nodexaThemeColors['accent'] }};
    --nx-accent2: {{ $nodexaThemeColors['success'] }};
    --nodexa-theme-accent: {{ $nodexaThemeColors['accent'] }};
    --nodexa-theme-secondary: {{ $nodexaThemeColors['secondary'] }};
}
</style>
<script>document.documentElement.setAttribute('data-nodexa-theme', @json($nodexaThemeState['active']));</script>
