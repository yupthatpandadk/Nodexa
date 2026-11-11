@extends('layouts.admin')

@section('title', 'Update Center')

@section('scripts')
    @parent
    <link rel="stylesheet" href="{{ asset('css/nodexa-update-center.css') }}?v=1">
@endsection

@section('content-header')
    <h1>Update Center <small>Versioner og sikker opdatering</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Update Center</li>
    </ol>
@endsection

@section('content')
@php
    $updateAvailable = $latest && $installed !== 'dev' && version_compare($latest, $installed, '>');
    $canUpdate = !$running && $latest && ($updateAvailable || $updateFailed);
    $retryIncomplete = $updateFailed && !$updateAvailable;
    $showProgress = $running || $updateFailed;
    $progressDetails = is_array($lastProgress ?? null) ? $lastProgress : [];
    $progressValue = max(0, min(100, (int) ($progressDetails['percent'] ?? ($running ? 1 : 0))));
    $progressStep = (string) ($progressDetails['step'] ?? ($running ? 'Forbereder opdatering…' : 'Tidligere opdatering blev afbrudt.'));
    $stateType = $running ? 'running' : ($updateFailed ? 'error' : ($updateAvailable ? 'available' : ($latest ? 'success' : 'unknown')));
    $stateLabel = $running ? 'Opdaterer' : ($updateFailed ? 'Opdatering fejlede' : ($updateAvailable ? 'Ny version' : ($latest ? 'Opdateret' : 'Ukendt status')));
    $lastReleases = array_slice($releases, 0, 10);
@endphp

<div class="nx-updates">
    <div class="nx-updates__heading">
        <div>
            <div class="nx-updates__eyebrow">Systemvedligeholdelse</div>
            <h2>Hold Nodexa opdateret</h2>
            <p>Få overblik over installeret version, tilgængelige opdateringer og tidligere ændringer ét sted.</p>
        </div>
        <div class="nx-updates__heading-actions">
            <a class="nx-updates__button nx-updates__button-subtle" href="{{ route('admin.updates') }}">
                <i class="fa fa-refresh" aria-hidden="true"></i> Tjek for opdateringer
            </a>
            <a class="nx-updates__button nx-updates__button-subtle" href="https://github.com/yupthatpandadk/Nodexa" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-github" aria-hidden="true"></i> GitHub <i class="fa fa-external-link" aria-hidden="true" style="font-size:10px"></i>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="nx-updates__notice" role="status"><i class="fa fa-check-circle" style="color:var(--nu-green)" aria-hidden="true"></i><div>{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div class="nx-updates__notice nx-updates__notice--error" role="alert"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><div>{{ session('error') }}</div></div>
    @endif

    <div class="nx-updates__grid">
        <section class="nx-updates__panel" aria-labelledby="nx-version-title">
            <div class="nx-updates__panel-head">
                <div>
                    <h3 id="nx-version-title"><i class="fa fa-code-fork" aria-hidden="true"></i> Versionsstatus</h3>
                    <p>Installeret udgave og tilgængelig opdatering</p>
                </div>
                <span id="nodexa-update-status" class="nx-updates__state nx-updates__state--{{ $stateType }}" role="status">{{ $stateLabel }}</span>
            </div>
            <div class="nx-updates__status-panel">
                <div class="nx-updates__current">
                    <div class="nx-updates__status-copy">
                        <div class="nx-updates__eyebrow">Nodexa Control Panel</div>
                        <h3>
                            @if($running) Installerer en opdatering
                            @elseif($updateFailed) Seneste opdatering fejlede
                            @elseif($updateAvailable) Nodexa {{ $latest }} er tilgængelig
                            @elseif($latest) Du er opdateret
                            @else Kunne ikke hente versionsstatus
                            @endif
                        </h3>
                        <p>
                            @if($running)
                                Opdateringsprocessen arbejder på serveren. Følg status nedenfor.
                            @elseif($updateFailed)
                                Nodexa kunne ikke gennemføre den forrige opdatering. Se fejlbeskeden og loggen, før du prøver igen.
                            @elseif($updateAvailable)
                                En nyere udgave er klar fra GitHub. Læs ændringerne, inden du installerer.
                            @elseif($latest)
                                Der er ikke registreret en nyere version på den valgte kanal.
                            @else
                                Forbindelsen til versionsserveren kan ikke bekræftes lige nu.
                            @endif
                        </p>
                    </div>
                </div>

                <dl class="nx-updates__version-grid">
                    <div class="nx-updates__version">
                        <dt>Installeret version</dt>
                        <dd>{{ $installed }}</dd>
                    </div>
                    <div class="nx-updates__version">
                        <dt>Seneste version</dt>
                        <dd class="{{ $updateAvailable ? 'nx-updates__version-highlight' : '' }}">{{ $latest ?: '—' }}</dd>
                    </div>
                    <div class="nx-updates__version">
                        <dt>Opdateringskanal</dt>
                        <dd>main <small>· GitHub</small></dd>
                    </div>
                </dl>

                @if($error)
                    <div class="nx-updates__notice nx-updates__notice--warning" role="alert">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                        <div><strong>Versionskontrol mislykkedes</strong><p>{{ $error }}</p></div>
                    </div>
                @endif

                @if($updateFailed)
                    <div class="nx-updates__notice nx-updates__notice--error" role="alert">
                        <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                        <div><strong>Opdateringen kunne ikke gennemføres</strong>
                            <p>Kontrollér loggen til højre eller nedenfor. Hvis Composer viser <code>vendor/composer/installed.php: Permission denied</code>, skal den beskyttede opdateringstjeneste og filrettighederne kontrolleres fra SSH.</p>
                        </div>
                    </div>
                @endif

                <div class="nx-updates__actions">
                    <form method="POST" id="nodexa-update-form" action="{{ route('admin.updates.install') }}" onsubmit="return confirm('Vil du installere den nyeste Nodexa-version fra GitHub?');">
                        @csrf
                        <button type="submit" class="nx-updates__button nx-updates__button-primary" {{ $canUpdate ? '' : 'disabled' }}>
                            <i class="fa {{ $running ? 'fa-spinner fa-spin' : ($canUpdate ? 'fa-download' : 'fa-check') }}" aria-hidden="true"></i>
                            {{ $running ? 'Opdatering i gang' : ($retryIncomplete ? 'Genoptag installation' : ($canUpdate ? 'Installer version '.$latest : 'Ingen opdatering')) }}
                        </button>
                    </form>
                    <a class="nx-updates__button" href="#nx-release-title"><i class="fa fa-list-ul" aria-hidden="true"></i> Se ændringer</a>
                </div>
                <p class="nx-updates__help"><i class="fa fa-shield" aria-hidden="true"></i> Opdateringer startes via den beskyttede Nodexa-opdateringstjeneste. Kontrolpanelet kan kortvarigt være i vedligeholdelsestilstand.</p>
            </div>

            <div class="nx-updates__progress {{ $updateFailed ? 'is-error' : '' }}" id="nodexa-update-progress" style="{{ $showProgress ? '' : 'display:none' }}" aria-live="polite">
                <div class="nx-updates__progress-header">
                    <h4 id="nodexa-progress-step">{{ $progressStep }}</h4>
                    <strong id="nodexa-progress-percent">{{ $updateFailed ? 'Fejlet' : $progressValue.'%' }}</strong>
                </div>
                <div class="nx-updates__track" id="nodexa-progress-track" role="progressbar" aria-label="Opdateringsfremdrift" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressValue }}">
                    <div class="nx-updates__track-fill" id="nodexa-progress-bar" style="width:{{ $progressValue }}%"></div>
                </div>
                <p class="nx-updates__progress-note">Fremdriften opdateres automatisk, så længe opdateringen kører.</p>
            </div>
        </section>

        <aside class="nx-updates__panel" aria-labelledby="nx-log-title">
            <div class="nx-updates__panel-head">
                <div>
                    <h3 id="nx-log-title"><i class="fa fa-terminal" aria-hidden="true"></i> Aktivitetslog</h3>
                    <p>Seneste opdateringsforsøg</p>
                </div>
                <button type="button" class="nx-updates__button nx-updates__button-small" id="nx-copy-log" {{ empty($log) ? 'disabled' : '' }}>
                    <i class="fa fa-files-o" aria-hidden="true"></i> Kopiér log
                </button>
            </div>
            <div class="nx-updates__log-body">
                <p class="nx-updates__log-meta"><i class="fa fa-info-circle" aria-hidden="true"></i> Viser de sidste loglinjer. Brug Opdater status for at hente nyere linjer.</p>
                <pre class="nx-updates__log-pre" id="nx-update-log">{{ $log ?: 'Der er endnu ikke registreret nogen opdateringslog.' }}</pre>
                <div class="nx-updates__log-actions">
                    <p>Log: <code>storage/logs/nodexa-update.log</code></p>
                    <a href="{{ route('admin.updates') }}"><i class="fa fa-refresh" aria-hidden="true"></i> Genindlæs</a>
                </div>

                <details class="nx-updates__helper" id="nx-ssh-help">
                    <summary><i class="fa fa-life-ring" aria-hidden="true"></i> Problemer med opdateringen?</summary>
                    <div class="nx-updates__helper-body">
                        <p>Hvis Update Center melder en rettighedsfejl, kan du installere den nyeste version fra din servers SSH-terminal med denne kommando. Det overskriver lokale Git-ændringer.</p>
                        <code class="nx-updates__command" id="nx-update-command">curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash -s -- update</code>
                        <button class="nx-updates__button nx-updates__button-small" style="margin-top:10px" type="button" id="nx-copy-command">
                            <i class="fa fa-clipboard" aria-hidden="true"></i> Kopiér kommando
                        </button>
                    </div>
                </details>
            </div>
        </aside>
    </div>

    <section class="nx-updates__panel nx-updates__release-panel" aria-labelledby="nx-release-title">
        <div class="nx-updates__panel-head">
            <div>
                <h3 id="nx-release-title"><i class="fa fa-list-ul" aria-hidden="true"></i> Versionshistorik</h3>
                <p>Ændringer, forbedringer og rettelser fra GitHub</p>
            </div>
            <a class="nx-updates__button nx-updates__button-small nx-updates__button-subtle" href="https://github.com/yupthatpandadk/Nodexa/blob/main/NODEXA_CHANGELOG.json" target="_blank" rel="noopener noreferrer">Alle versioner <i class="fa fa-external-link" aria-hidden="true"></i></a>
        </div>

        @forelse($lastReleases as $release)
            <details class="nx-updates__release" {{ $loop->first ? 'open' : '' }}>
                <summary class="nx-updates__release-summary">
                    <div>
                        <div class="nx-updates__release-title">
                            <strong>Nodexa {{ $release['version'] ?? '—' }}</strong>
                            @if(($release['version'] ?? '') === $installed)
                                <span class="nx-updates__release-label">Installeret</span>
                            @elseif($latest && ($release['version'] ?? '') === $latest)
                                <span class="nx-updates__release-label nx-updates__release-label--latest">Nyeste</span>
                            @endif
                        </div>
                        <p class="nx-updates__release-subtitle">{{ $release['title'] ?? 'Opdatering af Nodexa' }}</p>
                    </div>
                    <span class="nx-updates__release-side">
                        <time class="nx-updates__release-date">{{ $release['date'] ?? '' }}</time>
                        <i class="fa fa-chevron-down nx-updates__release-chevron" aria-hidden="true"></i>
                    </span>
                </summary>
                <div class="nx-updates__release-content">
                    @if(!empty($release['changes']) && is_array($release['changes']))
                        <ul>
                            @foreach($release['changes'] as $change)
                                <li>{{ $change }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p>Ingen detaljer registreret for denne version.</p>
                    @endif
                </div>
            </details>
        @empty
            <div class="nx-updates__empty"><i class="fa fa-folder-open-o" aria-hidden="true"></i>Ingen release notes tilgængelige lige nu.</div>
        @endforelse

        @if(count($releases) > count($lastReleases))
            <div class="nx-updates__release-footer">
                <span>Viser de seneste {{ count($lastReleases) }} af {{ count($releases) }} udgivelser</span>
                <a href="https://github.com/yupthatpandadk/Nodexa/blob/main/NODEXA_CHANGELOG.json" target="_blank" rel="noopener noreferrer">Se den komplette historik på GitHub</a>
            </div>
        @endif
    </section>
</div>

<script>
(function () {
    'use strict';
    var running = @json($running);
    var url = @json(route('admin.updates.progress'));
    var wrapper = document.getElementById('nodexa-update-progress');
    var step = document.getElementById('nodexa-progress-step');
    var percent = document.getElementById('nodexa-progress-percent');
    var bar = document.getElementById('nodexa-progress-bar');
    var track = document.getElementById('nodexa-progress-track');
    var status = document.getElementById('nodexa-update-status');

    function updateState(text, variant) {
        if (!status) return;
        status.textContent = text;
        status.className = 'nx-updates__state nx-updates__state--' + variant;
    }

    function pollProgress() {
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) throw new Error('Could not retrieve update progress');
                return response.json();
            })
            .then(function (data) {
                var value = Math.max(0, Math.min(100, Number.parseInt(data.percent, 10) || 0));
                if (wrapper) wrapper.style.display = 'block';
                if (bar) bar.style.width = value + '%';
                if (track) track.setAttribute('aria-valuenow', String(value));
                if (step) step.textContent = data.step || 'Opdaterer Nodexa…';
                if (percent) percent.textContent = value + '%';

                if (data.status === 'failed' || (!data.running && (data.status === 'queued' || data.status === 'running'))) {
                    if (wrapper) wrapper.classList.add('is-error');
                    if (percent) percent.textContent = 'Fejlet';
                    if (data.status !== 'failed' && step) step.textContent = 'Opdateringen stoppede uventet. Kontrollér loggen.';
                    updateState('Opdatering fejlede', 'error');
                    return;
                }

                if (data.status === 'completed' && !data.running) {
                    updateState('Opdateret', 'success');
                    if (step) step.textContent = 'Opdateringen er gennemført. Genindlæser…';
                    window.setTimeout(function () { window.location.reload(); }, 1200);
                    return;
                }

                if (data.running || data.status === 'queued') {
                    updateState('Opdaterer', 'running');
                    window.setTimeout(pollProgress, 1200);
                }
            })
            .catch(function () {
                if (running) window.setTimeout(pollProgress, 3000);
            });
    }

    function attachCopy(buttonId, sourceId, successText) {
        var button = document.getElementById(buttonId);
        var source = document.getElementById(sourceId);
        if (!button || !source) return;
        button.addEventListener('click', function () {
            var value = source.textContent || '';
            if (!value.trim() || !navigator.clipboard || !navigator.clipboard.writeText) return;
            navigator.clipboard.writeText(value).then(function () {
                var previous = button.innerHTML;
                button.textContent = successText;
                window.setTimeout(function () { button.innerHTML = previous; }, 1800);
            }).catch(function () { /* Clipboard may be blocked by the browser. */ });
        });
    }

    attachCopy('nx-copy-log', 'nx-update-log', 'Log kopieret');
    attachCopy('nx-copy-command', 'nx-update-command', 'Kommando kopieret');

    if (running) pollProgress();
})();
</script>
@endsection
