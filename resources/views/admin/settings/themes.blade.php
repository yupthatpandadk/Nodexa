@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'themes'])

@section('title', 'Temaer')

@section('content-header')
    <h1>Temaer <small>Skift Nodexas udseende på hele hjemmesiden.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.settings') }}">Settings</a></li>
        <li class="active">Temaer</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <style>
        .nx-theme-header {display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:center;margin-bottom:20px}
        .nx-theme-header p {margin:5px 0 0;color:#9badc5}
        .nx-theme-preview {display:grid;grid-template-columns:1fr minmax(200px,340px);gap:24px;align-items:center;padding:26px;border:1px solid var(--preview-border);border-radius:15px;background:linear-gradient(140deg,var(--preview-bg),var(--preview-card));margin-bottom:24px}
        .nx-theme-preview h3 {font-weight:800;font-size:23px;color:#f1f5fb;margin:8px 0}
        .nx-theme-preview p {color:#9eacc1;margin:0;line-height:1.6}
        .nx-theme-demo {border-radius:12px;border:1px solid var(--preview-border);background:var(--preview-card);padding:18px;display:grid;gap:14px}
        .nx-theme-demo-nav {display:flex;align-items:center;justify-content:space-between;gap:10px;color:#dce5f2;font-weight:800;font-size:13px}
        .nx-theme-demo-mark {display:inline-flex;align-items:center;justify-content:center;border-radius:7px;width:27px;height:27px;background:linear-gradient(135deg,var(--preview-accent),var(--preview-secondary));color:#fff;margin-right:6px}
        .nx-theme-demo-line {height:6px;border-radius:20px;background:var(--preview-border)}
        .nx-theme-demo-line.short {width:68%}
        .nx-theme-demo-btn {display:inline-block;padding:8px 15px;border-radius:8px;color:#fff;font-size:12px;font-weight:800;background:linear-gradient(135deg,var(--preview-accent),var(--preview-secondary))}
        .nx-theme-section-title {font-weight:800;margin:25px 0 12px;font-size:15px;color:#eaf0fa}
        .nx-theme-grid {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .nx-theme-choice {display:block;margin:0;cursor:pointer;position:relative}
        .nx-theme-choice input {position:absolute;left:12px;top:12px;opacity:0}
        .nx-theme-card {border:1px solid #304057;border-radius:12px;background:#0d1726;padding:14px;min-height:131px;transition:border-color .15s,box-shadow .15s}
        .nx-theme-choice:hover .nx-theme-card {border-color:#6a7b95}
        .nx-theme-choice input:focus-visible + .nx-theme-card {outline:2px solid #8bbcff;outline-offset:3px}
        .nx-theme-choice input:checked + .nx-theme-card {border-color:var(--nx-accent);box-shadow:0 0 0 1px var(--nx-accent)}
        .nx-theme-swatch {height:40px;border-radius:8px;margin-bottom:12px;display:flex;align-items:center;padding:10px;gap:6px}
        .nx-theme-swatch i {display:block;width:15px;height:15px;border-radius:50%;border:2px solid rgba(255,255,255,.2)}
        .nx-theme-card-title {display:flex;justify-content:space-between;align-items:center;color:#f4f7fb;font-size:13px;font-weight:800}
        .nx-theme-check {display:none;color:var(--nx-accent)}
        .nx-theme-choice input:checked + .nx-theme-card .nx-theme-check {display:inline-block}
        .nx-theme-card-desc {font-size:11px;line-height:1.5;color:#98a8bf;display:block;margin-top:6px;max-width:320px}
        .nx-theme-automatic {display:flex;align-items:flex-start;gap:12px;padding:18px;border:1px solid #2a3b53;border-radius:12px;background:#101a2b;margin-top:25px}
        .nx-theme-automatic input {margin-top:4px}
        .nx-theme-automatic strong {display:block;color:#f1f4f9;font-size:13px}
        .nx-theme-automatic span {color:#9fb0c6;display:block;margin-top:4px;font-weight:400;line-height:1.55}
        @media(max-width:1050px){.nx-theme-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:690px){.nx-theme-grid{grid-template-columns:1fr}.nx-theme-preview{grid-template-columns:1fr;padding:18px}}
    </style>

    <form method="POST" action="{{ route('admin.settings.themes.update') }}">
        @csrf
        @method('PATCH')

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-paint-brush"></i> Website Theme Manager</h3>
                <span class="pull-right label label-primary">Aktivt: {{ $presets[$themeState['active']]['name'] }}</span>
            </div>
            <div class="box-body">
                <div class="nx-theme-header">
                    <div>
                        <strong style="color:#f4f6fc;font-size:15px">Vælg et tema</strong>
                        <p>Det gemte tema anvendes på storefront, Client Area og Admin Area.</p>
                    </div>
                    <span class="text-muted">Temaer skifter uden serverrestart</span>
                </div>

                @php $preview = $presets[old('theme', $themeState['selected'])] ?? $themeState['colors']; @endphp
                <div class="nx-theme-preview" id="nxThemePreview"
                     style="--preview-accent:{{ $preview['accent'] }};--preview-secondary:{{ $preview['secondary'] }};--preview-bg:{{ $preview['bg'] }};--preview-card:{{ $preview['card'] }};--preview-border:{{ $preview['border'] }}">
                    <div>
                        <span style="font-size:10px;letter-spacing:1.6px;color:var(--preview-accent);font-weight:800">LIVE PREVIEW</span>
                        <h3 id="nxThemePreviewName">{{ $preview['name'] }}</h3>
                        <p>Se farvepaletten, inden du gemmer. Andre besøgende får først temaet efter du har gemt.</p>
                    </div>
                    <div class="nx-theme-demo" aria-hidden="true">
                        <div class="nx-theme-demo-nav"><div><span class="nx-theme-demo-mark">N</span>Nodexa</div><span>● ● ●</span></div>
                        <div class="nx-theme-demo-line"></div>
                        <div class="nx-theme-demo-line short"></div>
                        <div><span class="nx-theme-demo-btn">Start server</span></div>
                    </div>
                </div>

                <div class="nx-theme-section-title">Sæsontemaer</div>
                <div class="nx-theme-grid">
                    @foreach (['halloween','christmas'] as $key)
                        @php $theme = $presets[$key]; @endphp
                        <label class="nx-theme-choice">
                            <input type="radio" name="theme" value="{{ $key }}" data-palette='@json($theme)' {{ old('theme', $themeState['selected']) === $key ? 'checked' : '' }}>
                            <div class="nx-theme-card">
                                <div class="nx-theme-swatch" style="background:{{ $theme['bg'] }};border:1px solid {{ $theme['border'] }}">
                                    <i style="background:{{ $theme['accent'] }}"></i>
                                    <i style="background:{{ $theme['secondary'] }}"></i>
                                    <i style="background:{{ $theme['success'] }}"></i>
                                </div>
                                <div class="nx-theme-card-title">{{ $theme['name'] }} <i class="fa fa-check-circle nx-theme-check"></i></div>
                                <span class="nx-theme-card-desc">{{ $theme['description'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="nx-theme-section-title">Farvetemaer</div>
                <div class="nx-theme-grid">
                    @foreach ($presets as $key => $theme)
                        @continue(in_array($key, ['halloween','christmas']))
                        <label class="nx-theme-choice">
                            <input type="radio" name="theme" value="{{ $key }}" data-palette='@json($theme)' {{ old('theme', $themeState['selected']) === $key ? 'checked' : '' }}>
                            <div class="nx-theme-card">
                                <div class="nx-theme-swatch" style="background:{{ $theme['bg'] }};border:1px solid {{ $theme['border'] }}">
                                    <i style="background:{{ $theme['accent'] }}"></i>
                                    <i style="background:{{ $theme['secondary'] }}"></i>
                                    <i style="background:{{ $theme['success'] }}"></i>
                                </div>
                                <div class="nx-theme-card-title">{{ $theme['name'] }} <i class="fa fa-check-circle nx-theme-check"></i></div>
                                <span class="nx-theme-card-desc">{{ $theme['description'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <label class="nx-theme-automatic">
                    <input type="hidden" name="automatic" value="0">
                    <input type="checkbox" name="automatic" value="1" {{ old('automatic', $themeState['automatic']) ? 'checked' : '' }}>
                    <span>
                        <strong>Automatisk sæsontema</strong>
                        <span>Aktivér Halloween i oktober og juletema i december. Resten af året bruges det valgte farvetema. Deaktiver for at bruge dit valg hele året.</span>
                    </span>
                </label>

                <label class="nx-theme-automatic" style="margin-top:12px">
                    <input type="hidden" name="snowfall" value="0">
                    <input type="checkbox" name="snowfall" value="1" {{ old('snowfall', $themeState['snowfall']) ? 'checked' : '' }}>
                    <span>
                        <strong><i class="fa fa-snowflake-o" aria-hidden="true"></i> Faldende sne</strong>
                        <span>Slå animerede snefnug til eller fra på hjemmesiden, Client Area og Admin Area. Virker kun, når juletemaet er aktivt (også ved automatisk sæsontema). Liggende sne vises altid i juletemaet.</span>
                    </span>
                </label>
            </div>
            <div class="box-footer" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                <span class="text-muted">Aktuelt vist: {{ $presets[$themeState['active']]['name'] }}</span>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Gem tema</button>
            </div>
        </div>
    </form>

    <script>
    (function () {
        var preview = document.getElementById('nxThemePreview');
        var title = document.getElementById('nxThemePreviewName');
        if (!preview || !title) return;
        document.querySelectorAll('input[name="theme"]').forEach(function (option) {
            option.addEventListener('change', function () {
                if (!option.checked) return;
                var colors;
                try { colors = JSON.parse(option.getAttribute('data-palette')); } catch (error) { return; }
                preview.style.setProperty('--preview-accent', colors.accent);
                preview.style.setProperty('--preview-secondary', colors.secondary);
                preview.style.setProperty('--preview-bg', colors.bg);
                preview.style.setProperty('--preview-card', colors.card);
                preview.style.setProperty('--preview-border', colors.border);
                title.textContent = colors.name;
            });
        });
    })();
    </script>
@endsection
