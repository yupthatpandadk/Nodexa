@extends('layouts.admin')

@section('title')
    Fejlcenter
@endsection

@section('content-header')
    <h1>Fejlcenter<small>Diagnostik og sikre reparationsværktøjer til Nodexa Panel og Wings.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Fejlcenter</li>
    </ol>
@endsection

@section('content')
    @if (session('diagnostics_message'))
        <div class="alert alert-success"><i class="fa fa-check-circle"></i> {{ session('diagnostics_message') }}</div>
    @endif
    @if (session('diagnostics_error'))
        <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> {{ session('diagnostics_error') }}</div>
    @endif

    <style>
        .nx-health-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
        .nx-health-card { border:1px solid var(--nodexa-border); border-radius:12px; padding:15px; background:rgba(var(--nodexa-accent-rgb),.035); }
        .nx-health-card strong { display:block; font-size:25px; line-height:1; margin-bottom:7px; color:var(--nodexa-admin-text); }
        .nx-health-card span { color:var(--nodexa-admin-muted); font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
        .nx-check { display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid var(--nodexa-border); }
        .nx-check:last-child { border-bottom:0; }
        .nx-check-main { flex:1; min-width:0; }
        .nx-check-main strong { display:block; color:var(--nodexa-admin-text); margin-bottom:3px; }
        .nx-check-main small { color:var(--nodexa-admin-muted); line-height:1.45; }
        .nx-status { display:inline-flex; align-items:center; justify-content:center; width:29px; height:29px; min-width:29px; border-radius:50%; font-size:13px; }
        .nx-status-ok { color:#7ef7bf; background:rgba(46,204,113,.14); border:1px solid rgba(46,204,113,.28); }
        .nx-status-warning { color:#ffd36b; background:rgba(243,156,18,.14); border:1px solid rgba(243,156,18,.28); }
        .nx-status-error { color:#ff8686; background:rgba(231,76,60,.14); border:1px solid rgba(231,76,60,.28); }
        .nx-node { border:1px solid var(--nodexa-border); border-radius:12px; padding:16px; margin-bottom:12px; background:rgba(var(--nodexa-accent-rgb),.03); }
        .nx-node-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
        .nx-node-title { display:flex; align-items:center; gap:10px; }
        .nx-node-title h4 { margin:0 0 4px; color:var(--nodexa-admin-text); }
        .nx-node-meta { color:var(--nodexa-admin-muted); font-size:12px; word-break:break-all; }
        .nx-pill { border-radius:999px; padding:5px 9px; font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; white-space:nowrap; }
        .nx-pill-ok { color:#7ef7bf; border:1px solid rgba(46,204,113,.28); background:rgba(46,204,113,.12); }
        .nx-pill-warning { color:#ffd36b; border:1px solid rgba(243,156,18,.28); background:rgba(243,156,18,.12); }
        .nx-pill-error { color:#ff8686; border:1px solid rgba(231,76,60,.28); background:rgba(231,76,60,.12); }
        .nx-node-detail { margin:13px 0 0; color:var(--nodexa-admin-muted); }
        .nx-node-info { margin-top:10px; color:var(--nodexa-admin-muted); font-size:12px; }
        .nx-repair { margin-top:14px; border:1px solid var(--nodexa-border); border-radius:9px; overflow:hidden; }
        .nx-repair-head { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:8px 10px; background:rgba(var(--nodexa-accent-rgb),.055); }
        .nx-repair pre { margin:0!important; border:0!important; border-radius:0!important; padding:12px!important; font-size:11px; white-space:pre-wrap; overflow:auto; }
        .nx-log-line { border-left:2px solid #e74c3c; padding:9px 11px; margin-bottom:8px; background:rgba(231,76,60,.055); color:var(--nodexa-admin-muted); font-family:monospace; font-size:11px; word-break:break-word; }
        .nx-empty { text-align:center; padding:24px 12px; color:var(--nodexa-admin-muted); }
        @media(max-width:767px){
            .nx-health-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .nx-check { align-items:flex-start; flex-wrap:wrap; }
            .nx-check form { width:100%; padding-left:43px; }
            .nx-check form .btn { width:100%; }
            .nx-node-head { flex-direction:column; }
        }
    </style>

    <div class="row">
        <div class="col-md-7">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-heartbeat"></i> Nodexa Panel</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('admin.diagnostics') }}" class="btn btn-default btn-xs"><i class="fa fa-refresh"></i> Scan igen</a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="nx-health-summary">
                        <div class="nx-health-card"><strong>{{ $panelSummary['total'] }}</strong><span>Checks</span></div>
                        <div class="nx-health-card"><strong>{{ $panelSummary['ok'] }}</strong><span>OK</span></div>
                        <div class="nx-health-card"><strong>{{ $panelSummary['warning'] }}</strong><span>Advarsler</span></div>
                        <div class="nx-health-card"><strong>{{ $panelSummary['error'] }}</strong><span>Fejl</span></div>
                    </div>

                    @php
                        $fixLabels = [
                            'permissions' => 'Reparer permissions',
                            'storage-link' => 'Genskab storage-link',
                            'clear-cache' => 'Ryd cache',
                            'restart-queue' => 'Genstart Queue',
                            'restart-scheduler' => 'Genstart Scheduler',
                            'restart-web' => 'Genstart Web',
                            'local-wings' => 'Forsøg Wings recovery',
                        ];
                    @endphp

                    @foreach ($panelChecks as $check)
                        <div class="nx-check">
                            <span class="nx-status nx-status-{{ $check['status'] }}">
                                <i class="fa {{ $check['status'] === 'ok' ? 'fa-check' : ($check['status'] === 'warning' ? 'fa-exclamation' : 'fa-times') }}"></i>
                            </span>
                            <div class="nx-check-main">
                                <strong>{{ $check['name'] }}</strong>
                                <small>{{ $check['detail'] }}</small>
                            </div>
                            @if (!empty($check['fix']))
                                <form method="POST" action="{{ route('admin.diagnostics.fix') }}" onsubmit="return confirm('Kør denne sikre Nodexa-reparation nu?');">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $check['fix'] }}">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-wrench"></i> {{ $fixLabels[$check['fix']] ?? 'Fix' }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-file-text-o"></i> Seneste Laravel-fejl</h3>
                </div>
                <div class="box-body">
                    @forelse ($recentErrors as $error)
                        <div class="nx-log-line">{{ $error }}</div>
                    @empty
                        <div class="nx-empty"><i class="fa fa-check-circle"></i><br>Ingen ERROR/CRITICAL-linjer fundet i den seneste del af Laravel-loggen.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-server"></i> Wings / Nodes</h3>
                </div>
                <div class="box-body">
                    <div class="nx-health-summary">
                        <div class="nx-health-card"><strong>{{ $nodeSummary['total'] }}</strong><span>Nodes</span></div>
                        <div class="nx-health-card"><strong>{{ $nodeSummary['online'] }}</strong><span>Online</span></div>
                        <div class="nx-health-card"><strong>{{ $nodeSummary['warning'] }}</strong><span>Advarsler</span></div>
                        <div class="nx-health-card"><strong>{{ $nodeSummary['offline'] }}</strong><span>Offline</span></div>
                    </div>

                    @forelse ($nodes as $node)
                        <div class="nx-node">
                            <div class="nx-node-head">
                                <div class="nx-node-title">
                                    <span class="nx-status nx-status-{{ $node['status'] }}"><i class="fa fa-server"></i></span>
                                    <div>
                                        <h4>{{ $node['name'] }}</h4>
                                        <div class="nx-node-meta">{{ $node['address'] }}</div>
                                    </div>
                                </div>
                                <span class="nx-pill nx-pill-{{ $node['status'] }}">{{ $node['title'] }}</span>
                            </div>

                            <p class="nx-node-detail">{{ $node['detail'] }}</p>

                            @if (!empty($node['version']) || !empty($node['system']))
                                <div class="nx-node-info">
                                    @if (!empty($node['version']))<span><strong>Wings:</strong> {{ $node['version'] }}</span>@endif
                                    @if (!empty($node['system']))<span style="margin-left:10px;"><strong>System:</strong> {{ $node['system'] }}</span>@endif
                                </div>
                            @endif

                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:13px;">
                                <a href="{{ $node['configuration_url'] }}" class="btn btn-default btn-sm"><i class="fa fa-cog"></i> Node Configuration</a>
                                @if ($node['status'] === 'error')
                                    <button type="button" class="btn btn-primary btn-sm" data-toggle-repair="repair-{{ $node['id'] }}"><i class="fa fa-terminal"></i> Repair command</button>
                                @endif
                            </div>

                            @if ($node['status'] === 'error')
                                <div class="nx-repair" id="repair-{{ $node['id'] }}" style="display:none;">
                                    <div class="nx-repair-head">
                                        <strong>Safe Wings recovery</strong>
                                        <button type="button" class="btn btn-default btn-xs" data-copy-target="repair-code-{{ $node['id'] }}"><i class="fa fa-copy"></i> Kopiér</button>
                                    </div>
                                    <pre id="repair-code-{{ $node['id'] }}">{{ $node['repair_command'] }}</pre>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="nx-empty">Der er endnu ingen Nodes oprettet i Nodexa.</div>
                    @endforelse
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-shield"></i> Sådan virker Fix</h3></div>
                <div class="box-body text-muted">
                    <p>Fejlcenteret kan ikke køre vilkårlige shell-kommandoer. Panel-fixes er låst til en lille whitelist af Nodexa-reparationer.</p>
                    <p style="margin-bottom:0;">For eksterne Wings-nodes viser Nodexa en repair-kommando, som du selv kører på den pågældende Node. Hvis token/configuration er forkert, bruges <strong>Node Configuration</strong> til at generere en ny Auto-Deploy kommando.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            document.querySelectorAll('[data-toggle-repair]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var target = document.getElementById(button.getAttribute('data-toggle-repair'));
                    if (!target) return;
                    target.style.display = target.style.display === 'none' ? 'block' : 'none';
                });
            });

            document.querySelectorAll('[data-copy-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var target = document.getElementById(button.getAttribute('data-copy-target'));
                    if (!target) return;
                    var text = target.textContent || '';
                    navigator.clipboard.writeText(text).then(function () {
                        var original = button.innerHTML;
                        button.innerHTML = '<i class="fa fa-check"></i> Kopieret';
                        setTimeout(function () { button.innerHTML = original; }, 1400);
                    }).catch(function () {});
                });
            });
        })();
    </script>
@endsection
