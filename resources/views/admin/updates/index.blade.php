@extends('layouts.admin')

@section('title')
    Update Center
<script>
(function () {
    var wrap = document.getElementById('nodexa-update-progress');
    var bar = document.getElementById('nodexa-progress-bar');
    var percent = document.getElementById('nodexa-progress-percent');
    var step = document.getElementById('nodexa-progress-step');
    var running = {{ $running ? 'true' : 'false' }};

    function pollProgress() {
        fetch('{{ route('admin.updates.progress') }}', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var value = Math.max(0, Math.min(100, parseInt(data.percent || 0, 10)));
                if (data.running || data.status === 'completed' || data.status === 'failed') wrap.style.display = 'block';
                bar.style.width = value + '%';
                percent.textContent = value + '%';
                step.textContent = data.step || 'Updating Nodexa…';

                if (data.status === 'failed') {
                    bar.style.background = '#ef4444';
                    percent.style.color = '#ef4444';
                    return;
                }
                if (data.status === 'completed' && !data.running) {
                    setTimeout(function () { window.location.reload(); }, 1200);
                    return;
                }
                if (data.running) setTimeout(pollProgress, 1000);
            })
            .catch(function () { if (running) setTimeout(pollProgress, 2000); });
    }

    if (running) pollProgress();
})();
</script>
@endsection

@section('content-header')
    <h1>Update Center <small>Keep Nodexa up to date from GitHub.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Update Center</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-cloud-download"></i> Nodexa Updates</h3></div>
            <div class="box-body">
                <table class="table">
                    <tr><td><strong>Installed version</strong></td><td><code>{{ $installed }}</code></td></tr>
                    <tr><td><strong>GitHub version</strong></td><td>@if($latest)<code>{{ $latest }}</code>@else <span class="text-red">Unavailable</span>@endif</td></tr>
                    <tr><td><strong>Channel</strong></td><td><code>main</code></td></tr>
                    <tr><td><strong>Status</strong></td><td>
                        @if($running)
                            <span class="label label-warning">Updating</span>
                        @elseif($latest && $latest !== $installed)
                            <span class="label label-info">Update available</span>
                        @elseif($latest)
                            <span class="label label-success">Up to date</span>
                        @else
                            <span class="label label-danger">Could not check</span>
                        @endif
                    </td></tr>
                </table>
                @if($error)<div class="alert alert-warning" style="margin-top:15px;">{{ $error }}</div>@endif
            </div>
            <div class="box-footer">
                <div id="nodexa-update-progress" style="display:{{ $running ? 'block' : 'none' }};margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:7px;">
                        <strong id="nodexa-progress-step">{{ $running ? 'Updating Nodexa…' : 'Preparing update…' }}</strong>
                        <span id="nodexa-progress-percent" style="font-weight:700;color:#8b5cf6;">{{ $running ? '5%' : '0%' }}</span>
                    </div>
                    <div style="height:12px;background:#111827;border-radius:999px;overflow:hidden;border:1px solid #263149;">
                        <div id="nodexa-progress-bar" style="height:100%;width:{{ $running ? '5%' : '0%' }};border-radius:999px;background:linear-gradient(90deg,#6366f1,#8b5cf6,#0ea5e9);transition:width .45s ease;box-shadow:0 0 18px rgba(99,102,241,.4);"></div>
                    </div>
                </div>
                <form id="nodexa-update-form" method="POST" action="{{ route('admin.updates.install') }}" onsubmit="return confirm('Install the latest Nodexa update from GitHub?');">
                    {!! csrf_field() !!}
                    <button class="btn btn-primary" {{ $running ? 'disabled' : '' }}>
                        <i class="fa fa-download"></i> {{ $running ? 'Update running…' : 'Install latest update' }}
                    </button>
                    <a class="btn btn-default" href="{{ route('admin.updates') }}"><i class="fa fa-refresh"></i> Check again</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-terminal"></i> Update log</h3></div>
            <div class="box-body">
                <pre style="max-height:420px;overflow:auto;white-space:pre-wrap;">{{ $log ?: 'No update has been run yet.' }}</pre>
            </div>
        </div>
    </div>
</div>

@if(count($releases))
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list-alt"></i> Release notes</h3>
            </div>
            <div class="box-body">
                @foreach($releases as $release)
                    <div style="padding:16px 0;{{ !$loop->last ? 'border-bottom:1px solid #22304a;' : '' }}">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <strong style="font-size:17px;color:#f4f7fb;">Nodexa {{ $release['version'] ?? '?' }}</strong>
                            @if(($release['version'] ?? null) === $installed)
                                <span class="label label-success">Installeret</span>
                            @elseif($latest && ($release['version'] ?? null) === $latest)
                                <span class="label label-info">Nyeste</span>
                            @endif
                            <span class="text-muted">{{ $release['date'] ?? '' }}</span>
                        </div>
                        <div style="font-weight:600;margin:7px 0 8px;">{{ $release['title'] ?? '' }}</div>
                        <ul style="margin-bottom:0;padding-left:20px;">
                            @foreach(($release['changes'] ?? []) as $change)
                                <li style="margin:4px 0;">{{ $change }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif
@endsection
