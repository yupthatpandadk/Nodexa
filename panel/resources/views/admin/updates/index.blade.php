@extends('layouts.admin')

@section('title')
    Opdateringer
@endsection

@section('content-header')
    <h1>Opdateringer<small>Versioner, changelog og installation samlet ét sted.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Opdateringer</li>
    </ol>
@endsection

@section('content')
@php
    $releases = $changelog['versions'] ?? [];
    $latestRelease = collect($releases)->first(function ($release) use ($latest) {
        return (string) ($release['version'] ?? '') === (string) ($latest['version'] ?? '');
    });
    if (!$latestRelease && !empty($releases)) {
        $latestRelease = $releases[0];
    }
@endphp

<style>
    .nx-update-page{--nx-green:#42e9a6;--nx-blue:#4d8dff;--nx-border:rgba(110,153,190,.20);--nx-muted:#8fa4b6;--nx-surface:rgba(13,32,49,.82);--nx-surface-2:rgba(17,42,64,.78)}
    .nx-update-hero{position:relative;overflow:hidden;border:1px solid var(--nx-border);border-radius:18px;padding:24px;margin-bottom:18px;background:linear-gradient(135deg,rgba(41,104,173,.18),rgba(24,231,167,.08)),var(--nx-surface);box-shadow:0 18px 45px rgba(0,0,0,.18)}
    .nx-update-hero:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;right:-110px;top:-150px;background:rgba(77,141,255,.12);filter:blur(5px);pointer-events:none}
    .nx-update-top{display:flex;align-items:flex-start;justify-content:space-between;gap:22px;position:relative;z-index:1}.nx-update-title{display:flex;gap:15px;align-items:center}.nx-update-icon{width:52px;height:52px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:22px;background:linear-gradient(145deg,rgba(77,141,255,.25),rgba(66,233,166,.16));border:1px solid rgba(77,141,255,.32)}
    .nx-update-title h2{font-size:22px;margin:0 0 5px}.nx-update-title p{margin:0;color:var(--nx-muted)}.nx-status-pill{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:8px 12px;font-size:11px;font-weight:800;letter-spacing:.04em;white-space:nowrap}.nx-status-ready{background:rgba(66,233,166,.13);color:#6ff0bb;border:1px solid rgba(66,233,166,.28)}.nx-status-current{background:rgba(143,164,182,.11);color:#b8c7d3;border:1px solid rgba(143,164,182,.22)}.nx-status-running{background:rgba(255,190,74,.12);color:#ffd176;border:1px solid rgba(255,190,74,.28)}.nx-status-failed{background:rgba(255,91,91,.12);color:#ff9090;border:1px solid rgba(255,91,91,.28)}
    .nx-version-grid{display:grid;grid-template-columns:1fr 42px 1fr;gap:14px;align-items:stretch;margin-top:24px;position:relative;z-index:1}.nx-version-card{border:1px solid var(--nx-border);border-radius:14px;padding:17px 18px;background:rgba(4,15,25,.27)}.nx-version-label{font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:var(--nx-muted);margin-bottom:7px}.nx-version-number{font-size:27px;font-weight:750;line-height:1.1}.nx-version-meta{font-size:12px;color:var(--nx-muted);margin-top:8px}.nx-version-arrow{display:flex;align-items:center;justify-content:center;color:#6a89a3;font-size:16px}
    .nx-update-actions{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:18px;padding-top:17px;border-top:1px solid var(--nx-border);position:relative;z-index:1}.nx-update-message{display:flex;align-items:center;gap:9px;min-width:0}.nx-update-message strong{font-size:13px}.nx-action-buttons{display:flex;gap:9px;flex-wrap:wrap}.nx-action-buttons .btn{border-radius:9px;min-height:38px;padding:8px 14px;font-weight:650}
    .nx-update-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(270px,.7fr);gap:18px}.nx-card{border:1px solid var(--nx-border);border-radius:16px;background:var(--nx-surface);box-shadow:0 14px 35px rgba(0,0,0,.14);margin-bottom:18px;overflow:hidden}.nx-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 19px;border-bottom:1px solid var(--nx-border);background:rgba(255,255,255,.012)}.nx-card-head h3{margin:0;font-size:15px;font-weight:750}.nx-card-head small{color:var(--nx-muted)}.nx-card-body{padding:19px}
    .nx-whats-new{background:linear-gradient(145deg,rgba(66,233,166,.055),rgba(77,141,255,.04)),var(--nx-surface)}.nx-release-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px}.nx-release-version{font-size:12px;font-weight:800;color:#77a9ff;letter-spacing:.05em}.nx-release-head h3{margin:4px 0 0;font-size:20px}.nx-release-date{font-size:12px;color:var(--nx-muted);white-space:nowrap}.nx-change-list{list-style:none;padding:0;margin:0;display:grid;gap:9px}.nx-change-list li{position:relative;padding-left:22px;color:#c7d4de;line-height:1.5}.nx-change-list li:before{content:"\f00c";font-family:FontAwesome;position:absolute;left:0;top:2px;color:var(--nx-green);font-size:12px}
    .nx-history{display:grid;gap:11px}.nx-history-item{border:1px solid var(--nx-border);border-radius:13px;padding:14px 15px;background:rgba(255,255,255,.018)}.nx-history-item[open]{background:rgba(77,141,255,.035);border-color:rgba(77,141,255,.28)}.nx-history-item summary{cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px}.nx-history-item summary::-webkit-details-marker{display:none}.nx-history-main{display:flex;align-items:center;gap:11px;min-width:0}.nx-history-version{font-size:13px;font-weight:800;color:#9dbfff}.nx-history-title{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.nx-history-date{font-size:11px;color:var(--nx-muted);white-space:nowrap}.nx-history-body{padding:13px 0 1px 0;margin-top:12px;border-top:1px solid var(--nx-border)}
    .nx-type{font-size:9px;text-transform:uppercase;font-weight:800;letter-spacing:.06em;padding:4px 7px;border-radius:999px;border:1px solid rgba(77,141,255,.25);color:#91b7ff;background:rgba(77,141,255,.08)}.nx-type-fix{color:#ffbd77;border-color:rgba(255,173,77,.25);background:rgba(255,173,77,.08)}.nx-type-improvement{color:#c0a1ff;border-color:rgba(169,118,255,.25);background:rgba(169,118,255,.08)}
    .nx-source-list{display:grid;gap:14px}.nx-source-row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding-bottom:12px;border-bottom:1px solid var(--nx-border)}.nx-source-row:last-child{border-bottom:0;padding-bottom:0}.nx-source-row span{font-size:12px;color:var(--nx-muted)}.nx-source-row strong,.nx-source-row code{text-align:right;overflow-wrap:anywhere}.nx-info-note{font-size:12px;color:var(--nx-muted);line-height:1.55;margin:16px 0 0;padding:12px;border:1px solid var(--nx-border);border-radius:10px;background:rgba(255,255,255,.015)}
    .nx-log-shell{padding:0}.nx-log-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px}.nx-log-toggle{border:0;background:transparent;color:#aebfcd;padding:0;font-size:12px}.nx-log{margin:0;border:0;border-radius:0;min-height:170px;max-height:440px;overflow:auto;background:#071019;color:#9ce7c5;padding:17px;white-space:pre-wrap;font-size:12px;line-height:1.55}.nx-changelog-error{padding:13px 15px;border-radius:10px;background:rgba(255,91,91,.08);border:1px solid rgba(255,91,91,.2);color:#ffaaaa}
    @media(max-width:991px){.nx-update-layout{grid-template-columns:1fr}.nx-update-top{flex-direction:column}.nx-update-actions{align-items:flex-start;flex-direction:column}.nx-action-buttons{width:100%}.nx-action-buttons .btn,.nx-action-buttons form{flex:1}.nx-action-buttons form .btn{width:100%}}
    @media(max-width:620px){.nx-update-hero{padding:17px}.nx-update-title{align-items:flex-start}.nx-update-icon{width:44px;height:44px;flex:0 0 44px}.nx-version-grid{grid-template-columns:1fr;gap:9px}.nx-version-arrow{transform:rotate(90deg);height:18px}.nx-version-number{font-size:23px}.nx-update-message{align-items:flex-start}.nx-action-buttons{display:grid;grid-template-columns:1fr 1fr}.nx-card-head,.nx-card-body{padding:15px}.nx-release-head{flex-direction:column}.nx-history-item summary{align-items:flex-start}.nx-history-main{align-items:flex-start;flex-wrap:wrap}.nx-history-title{width:100%;white-space:normal}.nx-history-date{display:none}}
</style>

<div class="nx-update-page">
    @if (session('update_message'))
        <div class="alert alert-success">{{ session('update_message') }}</div>
    @endif
    @if (session('update_error'))
        <div class="alert alert-danger">{{ session('update_error') }}</div>
    @endif

    <section class="nx-update-hero">
        <div class="nx-update-top">
            <div class="nx-update-title">
                <div class="nx-update-icon"><i class="fa fa-cloud-download"></i></div>
                <div><h2>Nodexa Update Center</h2><p>Se hvad der er nyt, og hold panelet opdateret.</p></div>
            </div>
            @if (($state['status'] ?? '') === 'running')
                <span class="nx-status-pill nx-status-running" id="nodexa-update-badge"><i class="fa fa-spinner fa-spin"></i> OPDATERER</span>
            @elseif ($updateAvailable)
                <span class="nx-status-pill nx-status-ready" id="nodexa-update-badge"><i class="fa fa-arrow-circle-up"></i> OPDATERING KLAR</span>
            @else
                <span class="nx-status-pill nx-status-current" id="nodexa-update-badge"><i class="fa fa-check-circle"></i> OPDATERET</span>
            @endif
        </div>

        <div class="nx-version-grid">
            <div class="nx-version-card">
                <div class="nx-version-label">Installeret version</div>
                <div class="nx-version-number">v{{ $installed['version'] }}</div>
                <div class="nx-version-meta">Commit: <code>{{ $installed['commit'] ? substr($installed['commit'], 0, 12) : 'ukendt' }}</code></div>
            </div>
            <div class="nx-version-arrow"><i class="fa fa-long-arrow-right"></i></div>
            <div class="nx-version-card">
                <div class="nx-version-label">Seneste version</div>
                @if (!empty($latest['version']))
                    <div class="nx-version-number">v{{ $latest['version'] }}</div>
                    <div class="nx-version-meta">Hentet direkte fra Nodexas VERSION-fil på GitHub.</div>
                @else
                    <div class="nx-version-number">Utilgængelig</div>
                    <div class="nx-version-meta text-danger">{{ $latest['error'] ?? 'Kunne ikke hente versionsstatus.' }}</div>
                @endif
            </div>
        </div>

        <div class="nx-update-actions">
            <div class="nx-update-message">
                <i class="fa fa-info-circle"></i>
                <strong id="nodexa-update-message">
                    @if (($state['status'] ?? '') === 'running')
                        {{ $state['message'] }}
                    @elseif ($updateAvailable)
                        En nyere Nodexa-version er klar til installation.
                    @else
                        Du kører den nyeste kendte version.
                    @endif
                </strong>
            </div>
            <div class="nx-action-buttons">
                <a href="{{ route('admin.updates') }}" class="btn btn-default"><i class="fa fa-refresh"></i> Tjek igen</a>
                <form method="POST" action="{{ route('admin.updates.run') }}" onsubmit="return confirm('Vil du opdatere Nodexa nu? Panelet kan kortvarigt blive genindlæst.');">
                    @csrf
                    <button type="submit" class="btn btn-success" id="nodexa-update-button" {{ (!$updateAvailable || ($state['status'] ?? '') === 'running') ? 'disabled' : '' }}>
                        <i class="fa {{ ($state['status'] ?? '') === 'running' ? 'fa-spinner fa-spin' : ($updateAvailable ? 'fa-download' : 'fa-check') }}"></i>
                        <span id="nodexa-update-button-text">{{ ($state['status'] ?? '') === 'running' ? 'Opdaterer...' : ($updateAvailable ? 'Opdater nu' : 'Allerede opdateret') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <div class="nx-update-layout">
        <main>
            <section class="nx-card nx-whats-new">
                <div class="nx-card-head"><h3><i class="fa fa-star"></i> Hvad er nyt?</h3><small>Nyeste changelog</small></div>
                <div class="nx-card-body">
                    @if ($latestRelease)
                        <div class="nx-release-head">
                            <div>
                                <div class="nx-release-version">VERSION {{ $latestRelease['version'] }}</div>
                                <h3>{{ $latestRelease['title'] ?? 'Nodexa-opdatering' }}</h3>
                            </div>
                            <div class="nx-release-date">{{ $latestRelease['date'] ?? '' }}</div>
                        </div>
                        <ul class="nx-change-list">
                            @foreach (($latestRelease['changes'] ?? []) as $change)
                                <li>{{ $change }}</li>
                            @endforeach
                        </ul>
                    @elseif (!empty($changelog['error']))
                        <div class="nx-changelog-error"><i class="fa fa-exclamation-triangle"></i> {{ $changelog['error'] }}</div>
                    @else
                        <p class="text-muted" style="margin:0;">Der er endnu ingen changelog til denne version.</p>
                    @endif
                </div>
            </section>

            <section class="nx-card">
                <div class="nx-card-head"><h3><i class="fa fa-history"></i> Versionshistorik</h3><small>{{ count($releases) }} versioner</small></div>
                <div class="nx-card-body">
                    @if (!empty($releases))
                        <div class="nx-history">
                            @foreach ($releases as $index => $release)
                                @php $type = $release['type'] ?? 'feature'; @endphp
                                <details class="nx-history-item" {{ $index === 0 ? 'open' : '' }}>
                                    <summary>
                                        <div class="nx-history-main">
                                            <span class="nx-history-version">v{{ $release['version'] }}</span>
                                            <span class="nx-type {{ $type === 'fix' ? 'nx-type-fix' : ($type === 'improvement' ? 'nx-type-improvement' : '') }}">{{ $type === 'fix' ? 'Fix' : ($type === 'improvement' ? 'Forbedring' : 'Nyhed') }}</span>
                                            <span class="nx-history-title">{{ $release['title'] ?? 'Nodexa-opdatering' }}</span>
                                        </div>
                                        <span class="nx-history-date">{{ $release['date'] ?? '' }}</span>
                                    </summary>
                                    <div class="nx-history-body"><ul class="nx-change-list">@foreach (($release['changes'] ?? []) as $change)<li>{{ $change }}</li>@endforeach</ul></div>
                                </details>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted" style="margin:0;">Versionshistorikken kunne ikke indlæses.</p>
                    @endif
                </div>
            </section>

            <section class="nx-card">
                <div class="nx-card-head">
                    <h3><i class="fa fa-terminal"></i> Update-log</h3>
                    <button type="button" class="nx-log-toggle" id="nodexa-log-toggle"><i class="fa fa-chevron-up"></i> Skjul log</button>
                </div>
                <div class="nx-log-shell" id="nodexa-log-shell"><pre class="nx-log" id="nodexa-update-log">{{ $log }}</pre></div>
            </section>
        </main>

        <aside>
            <section class="nx-card">
                <div class="nx-card-head"><h3><i class="fa fa-code-fork"></i> Update-kilde</h3></div>
                <div class="nx-card-body">
                    <div class="nx-source-list">
                        <div class="nx-source-row"><span>Repository</span><code>{{ $installed['repository'] }}</code></div>
                        <div class="nx-source-row"><span>Branch</span><code>{{ $installed['branch'] }}</code></div>
                        <div class="nx-source-row"><span>Seneste status</span><strong id="nodexa-update-time">{{ $state['updated_at'] ?? 'Ingen status endnu' }}</strong></div>
                        <div class="nx-source-row"><span>Versionskilde</span><strong>Raw GitHub</strong></div>
                        <div class="nx-source-row"><span>Changelog</span><strong>{{ empty($changelog['error']) ? 'Forbundet' : 'Utilgængelig' }}</strong></div>
                    </div>
                    <p class="nx-info-note"><i class="fa fa-lock"></i> Update-knappen starter kun Nodexas dedikerede system-updater. Browseren kan ikke køre vilkårlige shell-kommandoer.</p>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var statusUrl = @json(route('admin.updates.status'));
            var initialStatus = @json($state['status'] ?? 'idle');
            var observedRunning = initialStatus === 'running';
            var finishedReloaded = false;
            var logToggle = document.getElementById('nodexa-log-toggle');
            var logShell = document.getElementById('nodexa-log-shell');

            if (logToggle && logShell) {
                logToggle.addEventListener('click', function () {
                    var hidden = logShell.style.display === 'none';
                    logShell.style.display = hidden ? '' : 'none';
                    logToggle.innerHTML = hidden ? '<i class="fa fa-chevron-up"></i> Skjul log' : '<i class="fa fa-chevron-down"></i> Vis log';
                });
            }

            function setBadge(badge, state, text, icon) {
                if (!badge) return;
                badge.className = 'nx-status-pill ' + state;
                badge.innerHTML = '<i class="fa ' + icon + '"></i> ' + text;
            }

            function setButton(button, available, running) {
                if (!button) return;
                var icon = button.querySelector('i');
                var text = document.getElementById('nodexa-update-button-text');
                button.disabled = running || !available;
                if (icon) icon.className = running ? 'fa fa-spinner fa-spin' : (available ? 'fa fa-download' : 'fa fa-check');
                if (text) text.textContent = running ? 'Opdaterer...' : (available ? 'Opdater nu' : 'Allerede opdateret');
            }

            function poll() {
                fetch(statusUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        var state = data.state || {};
                        var updateAvailable = data.update_available === true;
                        var badge = document.getElementById('nodexa-update-badge');
                        var message = document.getElementById('nodexa-update-message');
                        var log = document.getElementById('nodexa-update-log');
                        var time = document.getElementById('nodexa-update-time');
                        var button = document.getElementById('nodexa-update-button');

                        if (log) { log.textContent = data.log || 'Ingen update-log endnu.'; log.scrollTop = log.scrollHeight; }
                        if (time) time.textContent = state.updated_at || 'Ingen status endnu';

                        if (state.status === 'running') {
                            observedRunning = true;
                            setBadge(badge, 'nx-status-running', 'OPDATERER', 'fa-spinner fa-spin');
                            if (message) message.textContent = state.message || 'Opdatering kører.';
                            setButton(button, updateAvailable, true);
                            return;
                        }
                        if (state.status === 'failed' && observedRunning) {
                            setBadge(badge, 'nx-status-failed', 'FEJLET', 'fa-times-circle');
                            if (message) message.textContent = state.message || 'Opdateringen fejlede.';
                            setButton(button, updateAvailable, false);
                            if (!finishedReloaded) { finishedReloaded = true; setTimeout(function () { window.location.reload(); }, 1800); }
                            return;
                        }
                        if (state.status === 'success' && observedRunning) {
                            setBadge(badge, 'nx-status-ready', 'FÆRDIG', 'fa-check-circle');
                            if (message) message.textContent = state.message || 'Opdateringen er færdig.';
                            setButton(button, false, false);
                            if (!finishedReloaded) { finishedReloaded = true; setTimeout(function () { window.location.reload(); }, 1800); }
                            return;
                        }
                        if (updateAvailable) {
                            setBadge(badge, 'nx-status-ready', 'OPDATERING KLAR', 'fa-arrow-circle-up');
                            if (message) message.textContent = 'En nyere Nodexa-version er klar til installation.';
                        } else {
                            setBadge(badge, 'nx-status-current', 'OPDATERET', 'fa-check-circle');
                            if (message) message.textContent = 'Du kører den nyeste kendte version.';
                        }
                        setButton(button, updateAvailable, false);
                    })
                    .catch(function () {});
            }

            setInterval(poll, 2500);
            poll();
        })();
    </script>
@endsection
