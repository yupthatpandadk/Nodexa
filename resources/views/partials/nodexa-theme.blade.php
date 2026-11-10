@php
    $nodexaThemeState = app(\Pterodactyl\Services\Nodexa\ThemeService::class)->current();
    $nodexaThemeColors = $nodexaThemeState['colors'];
@endphp
<link rel="stylesheet" href="{{ asset('css/nodexa-themes.css') }}?v=3">
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
    @if($nodexaThemeState['active'] !== 'classic')
    --nodexa-panel-bg: {{ $nodexaThemeColors['bg'] }};
    --nodexa-panel-surface: {{ $nodexaThemeColors['surface'] }};
    --nodexa-panel-card: {{ $nodexaThemeColors['card'] }};
    --nodexa-panel-border: {{ $nodexaThemeColors['border'] }};
    --nodexa-panel-accent: {{ $nodexaThemeColors['accent'] }};
    --nodexa-panel-secondary: {{ $nodexaThemeColors['secondary'] }};
    @endif
}
</style>
<script>
    document.documentElement.setAttribute('data-nodexa-theme', @json($nodexaThemeState['active']));
    document.documentElement.setAttribute('data-nodexa-site-managed', @json($nodexaThemeState['active'] !== 'classic' ? '1' : '0'));
    document.documentElement.setAttribute('data-nodexa-snowfall', @json($nodexaThemeState['snowfall'] ? 'on' : 'off'));
</script>
