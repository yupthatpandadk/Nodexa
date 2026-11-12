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


    <style>
        /* Separate frontend layouts: never changes the Admin/Pterodactyl theme. */
        .nx-front-box {background:#0e1928;border:1px solid #293d55;border-radius:14px;overflow:hidden;margin:0 0 24px;color:#edf3fb}
        .nx-front-top {display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;padding:19px 21px;border-bottom:1px solid #293d55;background:#132137}
        .nx-front-top h2 {font-size:18px;font-weight:800;margin:0 0 4px;color:#eef5ff}
        .nx-front-top p {font-size:12px;color:#9eb0c7;margin:0;line-height:1.6}
        .nx-front-active {display:inline-flex;align-items:center;gap:7px;color:#8ad7be;font-size:11px;font-weight:750;padding:6px 10px;border:1px solid #3d6a64;border-radius:7px;background:#102b2c}
        .nx-front-body {padding:20px}
        .nx-front-grid {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .nx-front-option {border:1px solid #32455d;border-radius:11px;padding:10px;background:#101c2c;min-width:0}
        .nx-front-choice {display:block;margin:0;cursor:pointer;position:relative}
        .nx-front-choice input {position:absolute;left:10px;top:10px;z-index:1;accent-color:#79b0fa;width:17px;height:17px}
        .nx-front-choice input:focus-visible + .nx-front-card {outline:2px solid #88bdf8;outline-offset:3px}
        .nx-front-choice input:checked + .nx-front-card {border-color:#82b5f6;box-shadow:0 0 0 1px #82b5f6}
        .nx-front-card {border:1px solid transparent;border-radius:8px;padding:5px;min-height:211px}
        .nx-front-demo {position:relative;background:var(--front-bg);height:125px;border:1px solid var(--front-border,var(--front-accent));border-radius:6px;overflow:hidden;padding:10px}
        .nx-front-demo-nav {height:14px;border-bottom:1px solid color-mix(in srgb,var(--front-accent) 30%,transparent);display:flex;align-items:center;gap:5px}
        .nx-front-demo-brand {height:10px;width:10px;border-radius:3px;background:var(--front-accent)}
        .nx-front-demo-navline {width:17%;height:3px;border-radius:2px;background:color-mix(in srgb,var(--front-accent) 37%,transparent)}
        .nx-front-demo-right {height:6px;width:26px;background:var(--front-accent);border-radius:3px;margin-left:auto}
        .nx-front-demo-main {display:flex;align-items:center;gap:12px;height:87px}
        .nx-front-demo-copy {flex:1}
        .nx-front-demo-label {width:26%;height:3px;background:var(--front-accent);margin:0 0 11px;border-radius:2px}
        .nx-front-demo-heading {width:87%;height:8px;background:#e9f1ff;border-radius:2px;opacity:.8;margin-bottom:5px}
        .nx-front-demo-heading.two {width:67%;opacity:.56}
        .nx-front-demo-description {height:3px;width:89%;background:#788aa2;opacity:.55;margin:8px 0 9px;border-radius:2px}
        .nx-front-demo-button {height:10px;width:46%;border-radius:3px;background:var(--front-accent)}
        .nx-front-demo-product {width:35%;height:62px;border:1px solid color-mix(in srgb,var(--front-accent) 42%,transparent);border-radius:5px;background:var(--front-surface);padding:9px}
        .nx-front-demo-product:before {content:'';display:block;height:6px;width:57%;background:var(--front-accent);border-radius:3px;margin-bottom:7px}
        .nx-front-demo-product:after {content:'';display:block;width:87%;height:25px;border-radius:3px;background:linear-gradient(140deg,var(--front-surface),var(--front-secondary));opacity:.4}
        .nx-front-option[data-variant="latch"] .nx-front-demo {border-radius:3px}
        .nx-front-option[data-variant="latch"] .nx-front-demo-product {border-radius:2px}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo {background:radial-gradient(ellipse at top right,color-mix(in srgb,var(--front-accent) 25%,transparent),transparent 80%),var(--front-bg);border-radius:12px}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-product {border-radius:10px}
        .nx-front-option[data-variant="horizon"] .nx-front-demo {border-radius:3px}
        .nx-front-option[data-variant="dinonode"] .nx-front-demo-product {border-color:var(--front-accent)}
        .nx-front-title {display:flex;align-items:center;justify-content:space-between;gap:7px;margin:10px 0 5px}
        .nx-front-title strong {font-size:13px;color:#f0f5ff}
        .nx-front-title small {font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#84a1c1}
        .nx-front-desc {font-size:11px;color:#9eb0c5;line-height:1.6;margin:0 0 5px;min-height:52px}
        .nx-front-previewlink {display:inline-flex;align-items:center;gap:5px;padding:6px 4px 4px;color:#a8cef9;font-size:11px;font-weight:700;text-decoration:none}
        .nx-front-previewlink:hover {text-decoration:underline;color:#e6f2ff}
        .nx-front-bottom {border-top:1px solid #283b53;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:13px;flex-wrap:wrap}
        .nx-front-bottom p {margin:0;max-width:640px;font-size:11px;color:#9fb1c7;line-height:1.65}
        .nx-front-bottom .btn {min-height:38px;border-radius:7px;font-weight:800}
        .nx-front-legal {color:#9badc7;font-size:11px;line-height:1.6;margin:13px 1px 0}
        /* The small previews indicate each layout's different information architecture. */
        .nx-front-option[data-variant="latch"] .nx-front-demo-nav {border-bottom:2px solid var(--front-accent)}
        .nx-front-option[data-variant="latch"] .nx-front-demo-main {gap:4px}
        .nx-front-option[data-variant="latch"] .nx-front-demo-copy {flex:1.3}
        .nx-front-option[data-variant="latch"] .nx-front-demo-product {flex:0 0 42%;height:74px;border-radius:2px}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-nav {width:80%;margin:0 auto;padding:0 8px;border-radius:8px;background:var(--front-surface)}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-main {flex-direction:column;justify-content:center;gap:6px}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-copy {flex:0 0 auto;width:75%;text-align:center}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-label {margin:0 auto 7px}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-heading {margin:0 auto 4px}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-description {display:none}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-button {margin:5px auto 0;height:6px}
        .nx-front-option[data-variant="xlumia"] .nx-front-demo-product {width:65%;height:23px}
        .nx-front-option[data-variant="horizon"] .nx-front-demo:before {content:'';display:block;position:absolute;top:0;left:0;right:0;height:5px;background:var(--front-surface)}
        .nx-front-option[data-variant="horizon"] .nx-front-demo-nav {margin-top:5px;border-bottom:2px solid var(--front-accent)}
        .nx-front-option[data-variant="horizon"] .nx-front-demo-main {align-items:stretch;gap:0}
        .nx-front-option[data-variant="horizon"] .nx-front-demo-copy {padding:17px 8px 0 0}
        .nx-front-option[data-variant="horizon"] .nx-front-demo-product {height:auto;border-radius:1px}
        .nx-front-option[data-variant="dinonode"] .nx-front-demo-main {flex-direction:row-reverse}
        .nx-front-option[data-variant="dinonode"] .nx-front-demo-copy {padding-left:8px}
        .nx-front-option[data-variant="dinonode"] .nx-front-demo-product {width:43%;height:77px;border-radius:9px;border:2px solid var(--front-accent)}
        .nx-front-option[data-variant="dinonode"] .nx-front-demo-product:after {height:35px}
        /* True page previews replace misleading identical wireframes. */
        .nx-front-real-preview {position:relative;height:156px;background:#0a0d14;border:1px solid #34435a;border-radius:7px;overflow:hidden;isolation:isolate}
        .nx-front-real-preview iframe {position:absolute;top:0;left:0;width:1200px;height:665px;transform:scale(.246);transform-origin:top left;border:0;pointer-events:none;user-select:none}
        .nx-front-real-tag {position:absolute;right:7px;bottom:7px;z-index:2;padding:4px 6px;border:1px solid #394860;border-radius:4px;background:rgba(8,15,25,.92);color:#e3edf9;font-size:9px;font-weight:850;letter-spacing:.03em}
        @media(max-width:1100px){.nx-front-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:650px){.nx-front-grid{grid-template-columns:1fr}.nx-front-body{padding:12px}.nx-front-top{padding:15px}.nx-front-bottom{padding:14px}.nx-front-bottom .btn{width:100%}.nx-front-card{min-height:auto}.nx-front-desc{min-height:auto}}
    </style>

    <form method="POST" action="{{ route('admin.settings.themes.frontend.update') }}">
        @csrf
        @method('PATCH')
        <section class="nx-front-box" aria-labelledby="nx-front-heading">
            <div class="nx-front-top">
                <div>
                    <h2 id="nx-front-heading"><i class="fa fa-desktop" aria-hidden="true"></i> Frontend Theme Center</h2>
                    <p>Se den faktiske forside for hvert valg – ikke en farvelagt demonstration. Latch, Xlumia, Horizon og DinoNode er foreløbigt selvstændige Nodexa-varianter, ikke de købte originaltemplates.</p>
                </div>
                <div class="nx-front-active"><i class="fa fa-circle" aria-hidden="true"></i> Aktivt: {{ $frontendPresets[$frontendSelected]['name'] }}</div>
            </div>

            <div class="nx-front-body">
                <div class="nx-front-grid">
                    @foreach($frontendPresets as $key => $preset)
                        <div class="nx-front-option" data-variant="{{ $key }}"
                             style="--front-bg:{{ $preset['background'] }};--front-surface:{{ $preset['surface'] }};--front-accent:{{ $preset['accent'] }};--front-secondary:{{ $preset['secondary'] }}">
                            <label class="nx-front-choice">
                                <input type="radio" name="frontend_theme" value="{{ $key }}"
                                       {{ old('frontend_theme', $frontendSelected) === $key ? 'checked' : '' }}
                                       aria-label="{{ $preset['name'] }}">
                                <div class="nx-front-card">
                                    <div class="nx-front-real-preview">
                                        <iframe
                                            title="Live forhåndsvisning af {{ $preset['name'] }}"
                                            src="{{ route('store.home', ['frontend_theme_preview' => $key]) }}"
                                            loading="lazy"
                                            tabindex="-1"
                                            aria-hidden="true"
                                            referrerpolicy="same-origin"
                                        ></iframe>
                                        <span class="nx-front-real-tag"><i class="fa fa-desktop"></i> FAKTISK FORSIDE</span>
                                    </div>
                                    <div class="nx-front-title">
                                        <strong>{{ $preset['name'] }}</strong>
                                        @if($key === $frontendSelected)
                                            <small style="color:#85dcaf"><i class="fa fa-check-circle"></i> Aktivt</small>
                                        @elseif($key === 'classic')
                                            <small>Original</small>
                                        @endif
                                    </div>
                                    <p class="nx-front-desc">{{ $preset['description'] }}</p>
                                </div>
                            </label>
                            <a class="nx-front-previewlink" target="_blank" rel="noopener noreferrer"
                               href="{{ route('store.home', ['frontend_theme_preview' => $key]) }}">
                                <i class="fa fa-eye" aria-hidden="true"></i> Forhåndsvis frontend
                            </a>
                        </div>
                    @endforeach
                </div>
                <p class="nx-front-legal">
                    <i class="fa fa-info-circle"></i>
                    Latch-, Xlumia-, Horizon- og DinoNode-stil er selvstændige Nodexa-design inspireret af hostingbranchen.
                    Det er ikke de originale betalte templates eller deres billeder, kode eller licens.
                </p>
            </div>

            <div class="nx-front-bottom">
                <p><strong style="color:#e8effa">Det nuværende design bevares.</strong>
                    Skift tilbage til Nodexa Original når som helst. De nye design ændrer sidestruktur,
                    men bevarer kontrolpanelet, Admin Area, ordreflow, betalinger, produkter og data.
                </p>
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-check" aria-hidden="true"></i> Gem frontend-tema
                </button>
            </div>
        </section>
    </form>

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
