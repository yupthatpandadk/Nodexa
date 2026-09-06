@extends('layouts.admin')

@section('title')
    Opdateringer
@endsection

@section('content-header')
    <h1>Opdateringer<small>Hold Nodexa synkroniseret med GitHub.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Opdateringer</li>
    </ol>
@endsection

@section('content')
    @if (session('update_message'))
        <div class="alert alert-success">{{ session('update_message') }}</div>
    @endif
    @if (session('update_error'))
        <div class="alert alert-danger">{{ session('update_error') }}</div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-cloud-download"></i> Nodexa Update Center</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="text-muted text-uppercase" style="font-size:11px;font-weight:700;letter-spacing:.08em;">Installeret</p>
                            <h3 style="margin-top:0;">v{{ $installed['version'] }}</h3>
                            <p>
                                Commit:
                                <code>{{ $installed['commit'] ? substr($installed['commit'], 0, 12) : 'ukendt' }}</code>
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted text-uppercase" style="font-size:11px;font-weight:700;letter-spacing:.08em;">GitHub</p>
                            @if (!empty($latest['version']))
                                <h3 style="margin-top:0;">v{{ $latest['version'] }}</h3>
                                @if (!empty($latest['commit']))
                                    <p>Commit: <code>{{ substr($latest['commit'], 0, 12) }}</code></p>
                                    <p>{{ \Illuminate\Support\Str::limit($latest['message'] ?? 'Ingen commit-besked.', 120) }}</p>
                                @else
                                    <p class="text-muted">Commit-data er midlertidigt utilgængelig.</p>
                                @endif
                                @if (!empty($latest['warning']))
                                    <p class="text-warning" style="margin-bottom:0;"><i class="fa fa-exclamation-triangle"></i> {{ $latest['warning'] }}</p>
                                @endif
                            @elseif (!empty($latest['commit']))
                                <h3 style="margin-top:0;">{{ substr($latest['commit'], 0, 12) }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($latest['message'] ?? 'Ingen commit-besked.', 120) }}</p>
                            @else
                                <h3 style="margin-top:0;">Utilgængelig</h3>
                                <p class="text-danger">{{ $latest['error'] ?? 'Kunne ikke hente GitHub-status.' }}</p>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                        <div>
                            @if (($state['status'] ?? '') === 'running')
                                <span class="label label-warning" id="nodexa-update-badge">OPDATERER</span>
                                <strong style="margin-left:8px;" id="nodexa-update-message">{{ $state['message'] }}</strong>
                            @elseif ($updateAvailable)
                                <span class="label label-success" id="nodexa-update-badge">OPDATERING KLAR</span>
                                <strong style="margin-left:8px;" id="nodexa-update-message">En nyere version findes på GitHub.</strong>
                            @else
                                <span class="label label-default" id="nodexa-update-badge">OPDATERET</span>
                                <strong style="margin-left:8px;" id="nodexa-update-message">Nodexa er på den nyeste kendte version.</strong>
                            @endif
                        </div>

                        <div style="display:flex;gap:8px;">
                            <a href="{{ route('admin.updates') }}" class="btn btn-default">
                                <i class="fa fa-refresh"></i> Tjek igen
                            </a>
                            <form method="POST" action="{{ route('admin.updates.run') }}" style="display:inline;" onsubmit="return confirm('Vil du opdatere Nodexa fra GitHub nu? Panelet kan kortvarigt blive genindlæst.');">
                                @csrf
                                <button type="submit" class="btn btn-success" id="nodexa-update-button" {{ (!$updateAvailable || ($state['status'] ?? '') === 'running') ? 'disabled' : '' }}>
                                    <i class="fa {{ ($state['status'] ?? '') === 'running' ? 'fa-spinner fa-spin' : ($updateAvailable ? 'fa-download' : 'fa-check') }}"></i>
                                    <span id="nodexa-update-button-text">{{ ($state['status'] ?? '') === 'running' ? 'Opdaterer...' : ($updateAvailable ? 'Opdater nu' : 'Allerede opdateret') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-terminal"></i> Update-log</h3>
                </div>
                <div class="box-body" style="padding:0;">
                    <pre id="nodexa-update-log" style="margin:0;border:0;border-radius:0;min-height:260px;max-height:520px;overflow:auto;background:#091019;color:#9ce7c5;padding:18px;white-space:pre-wrap;">{{ $log }}</pre>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Update-kilde</h3>
                </div>
                <div class="box-body">
                    <dl>
                        <dt>Repository</dt>
                        <dd><code>{{ $installed['repository'] }}</code></dd>
                        <dt style="margin-top:12px;">Branch</dt>
                        <dd><code>{{ $installed['branch'] }}</code></dd>
                        <dt style="margin-top:12px;">Seneste status</dt>
                        <dd id="nodexa-update-time">{{ $state['updated_at'] ?? 'Ingen status endnu' }}</dd>
                    </dl>
                    <p class="text-muted" style="margin-top:18px;">
                        Update-knappen kan kun starte Nodexas dedikerede systemd-updater. Den kan ikke køre vilkårlige shell-kommandoer fra browseren.
                    </p>
                </div>
            </div>
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

            function setButton(button, available, running) {
                if (!button) return;

                var icon = button.querySelector('i');
                var text = document.getElementById('nodexa-update-button-text');
                button.disabled = running || !available;

                if (icon) {
                    icon.className = running
                        ? 'fa fa-spinner fa-spin'
                        : (available ? 'fa fa-download' : 'fa fa-check');
                }

                if (text) {
                    text.textContent = running ? 'Opdaterer...' : (available ? 'Opdater nu' : 'Allerede opdateret');
                }
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

                        if (log) {
                            log.textContent = data.log || 'Ingen update-log endnu.';
                            log.scrollTop = log.scrollHeight;
                        }
                        if (time) time.textContent = state.updated_at || 'Ingen status endnu';

                        if (state.status === 'running') {
                            observedRunning = true;
                            if (badge) { badge.className = 'label label-warning'; badge.textContent = 'OPDATERER'; }
                            if (message) message.textContent = state.message || 'Opdatering kører.';
                            setButton(button, updateAvailable, true);
                            return;
                        }

                        if (state.status === 'failed' && observedRunning) {
                            if (badge) { badge.className = 'label label-danger'; badge.textContent = 'FEJLET'; }
                            if (message) message.textContent = state.message || 'Opdateringen fejlede.';
                            setButton(button, updateAvailable, false);
                            if (!finishedReloaded) {
                                finishedReloaded = true;
                                setTimeout(function () { window.location.reload(); }, 1800);
                            }
                            return;
                        }

                        if (state.status === 'success' && observedRunning) {
                            if (badge) { badge.className = 'label label-success'; badge.textContent = 'FÆRDIG'; }
                            if (message) message.textContent = state.message || 'Opdateringen er færdig.';
                            setButton(button, false, false);
                            if (!finishedReloaded) {
                                finishedReloaded = true;
                                setTimeout(function () { window.location.reload(); }, 1800);
                            }
                            return;
                        }

                        if (updateAvailable) {
                            if (badge) { badge.className = 'label label-success'; badge.textContent = 'OPDATERING KLAR'; }
                            if (message) message.textContent = 'En nyere version findes på GitHub.';
                        } else {
                            if (badge) { badge.className = 'label label-default'; badge.textContent = 'OPDATERET'; }
                            if (message) message.textContent = 'Nodexa er på den nyeste kendte version.';
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
