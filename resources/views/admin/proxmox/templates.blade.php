@extends('layouts.admin')
@section('title', 'Proxmox VM Templates')

@section('content-header')
<h1>VM Templates <small>Færdige OS-skabeloner til Proxmox VE</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li><a href="{{ route('admin.proxmox') }}">Proxmox VE</a></li><li class="active">VM Templates</li></ol>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> {{ $errors->first() }}</div>
@endif
@if(!$ready)
    <div class="alert alert-warning">Databasetabellerne til VM Templates mangler. Kør Nodexas normale opdatering, som anvender migrations.</div>
@else
<div class="pve-vm-intro">
    <div><div class="pve-vm-label">NODEXA / PROXMOX</div><h2>Opret VM på få klik</h2><p>Vælg en OS-skabelon, angiv et navn, og lad Nodexa oprette en fuld klon og starte den automatisk, når Proxmox er færdig med kloningen.</p></div>
    <a class="btn btn-default" href="{{ route('admin.proxmox') }}"><i class="fa fa-plug"></i> Mine Proxmox-forbindelser</a>
</div>
<div class="row">
    <div class="col-lg-8">
        <div class="pve-vm-section-header">
            <div><div class="pve-vm-label">OS KATALOG</div><h3>Premade VM Templates</h3></div>
            <span class="pve-vm-count">{{ $mapped->count() }} aktive skabeloner</span>
        </div>
        <div class="pve-vm-grid">
            @foreach($presets as $key => $preset)
                @php $links = $mapped->where('preset_key', $key); @endphp
                <article class="pve-vm-card">
                    <div class="pve-vm-card-head">
                        <div class="pve-vm-icon {{ $preset['icon'] === 'windows' ? 'is-windows' : '' }}">
                            <i class="fa {{ $preset['icon'] === 'windows' ? 'fa-windows' : 'fa-linux' }}" aria-hidden="true"></i>
                        </div>
                        <span class="pve-vm-tag">{{ $preset['group'] }}</span>
                    </div>
                    <h4>{{ $preset['name'] }}</h4>
                    <p>{{ $preset['description'] }}</p>
                    @forelse($links as $link)
                        <div class="pve-vm-source">
                            <span><i class="fa fa-server"></i> {{ $link->connection_name }} / {{ $link->node }} / #{{ $link->source_vmid }}</span>
                            <form method="POST" action="{{ route('admin.proxmox.templates.delete', $link->id) }}" onsubmit="return confirm('Fjern denne skabelon fra Nodexa? Allerede oprettede VM’er bliver ikke berørt.');">
                                @csrf @method('DELETE')
                                <button type="submit" title="Fjern skabelon" aria-label="Fjern skabelon" class="pve-vm-remove"><i class="fa fa-times" aria-hidden="true"></i></button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('admin.proxmox.templates.deploy', $link->id) }}" class="pve-vm-launch" onsubmit="return confirm('Opret en ny, uafhængig Proxmox-VM fra denne skabelon og start den automatisk, når kloningen er færdig?');">
                            @csrf
                            <label for="pve-name-{{ $link->id }}">Navn på din nye VM</label>
                            <input id="pve-name-{{ $link->id }}" type="text" name="name" class="form-control" required minlength="2" maxlength="63" pattern="[a-z0-9][a-z0-9-]*[a-z0-9]" placeholder="fx {{ $key }}-01" autocomplete="off">
                            <input type="hidden" name="auto_start" value="1">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-play-circle"></i> Opret og start VM</button>
                        </form>
                    @empty
                        <div class="pve-vm-not-ready"><i class="fa fa-link"></i> Forbind først en Proxmox-template for at aktivere dette OS.</div>
                    @endforelse
                </article>
            @endforeach
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box box-primary pve-vm-setup">
            <div class="box-header with-border"><h3 class="box-title">Forbind din OS-skabelon</h3></div>
            <form method="POST" action="{{ route('admin.proxmox.templates.store') }}">
                @csrf
                <div class="box-body">
                    <p>Vælg en eksisterende <strong>QEMU VM-template</strong> i Proxmox. Nodexa bruger den som grundlag for nye VM’er.</p>
                    <div class="form-group">
                        <label for="pve-tpl-connection">Proxmox-forbindelse</label>
                        <select name="connection_id" id="pve-tpl-connection" class="form-control" required>
                            <option value="">Vælg forbindelse</option>
                            @foreach($connections as $connection)
                                <option value="{{ $connection->id }}" {{ (int) old('connection_id') === $connection->id ? 'selected' : '' }}>{{ $connection->name }}{{ $connection->status !== 'connected' ? ' (ikke testet)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pve-tpl-preset">Operativsystem</label>
                        <select name="preset_key" id="pve-tpl-preset" class="form-control" required>
                            @foreach($presets as $key => $preset)
                                <option value="{{ $key }}" {{ old('preset_key') === $key ? 'selected' : '' }}>{{ $preset['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pve-tpl-node">Node</label>
                        <input class="form-control" type="text" id="pve-tpl-node" name="node" value="{{ old('node') }}" placeholder="pve" maxlength="80" required>
                        <p class="help-block">Det præcise navn på noden i dit Proxmox-panel.</p>
                    </div>
                    <div class="form-group">
                        <label for="pve-tpl-vmid">VM-template ID</label>
                        <input class="form-control" type="number" min="100" max="999999999" id="pve-tpl-vmid" name="source_vmid" value="{{ old('source_vmid') }}" placeholder="9000" required>
                        <p class="help-block">Opret eller konvertér en klargjort VM til template i Proxmox først. Nodexa kontrollerer, at VM-ID'et faktisk er en template.</p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" {{ $connections->isEmpty() ? 'disabled' : '' }}><i class="fa fa-check-circle"></i> Aktivér OS-skabelon</button>
                </div>
            </form>
        </div>
        <div class="box box-default pve-vm-help">
            <div class="box-header with-border"><h3 class="box-title">Sådan virker det</h3></div>
            <div class="box-body">
                <ol>
                    <li>Forbind Proxmox med et API-token, som har rettigheder til kloning og VM-start.</li>
                    <li>Opret en ren Windows/Linux-template på Proxmox (gerne med cloud-init og QEMU Guest Agent).</li>
                    <li>Vælg OS, node og VM-ID i formularen ovenfor.</li>
                    <li>Skriv et VM-navn i kataloget, og klik <strong>Opret og start VM</strong>.</li>
                </ol>
                <p class="text-muted">Kloningen udføres i baggrunden. Det kan tage tid, før VM'en er startet. En fuld klon får egne diske og påvirker ikke kildeskabelonen.</p>
                <p class="text-muted">VM'ens CPU, RAM, netværk og disk bliver arvet fra Proxmox-skabelonen. Windows kræver en gyldig licens og korrekt sysprep.</p>
            </div>
        </div>
    </div>
</div>

<div class="box box-default pve-vm-jobs">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-tasks"></i> VM-oprettelser</h3>
        <div class="box-tools pull-right">
            <form method="POST" action="{{ route('admin.proxmox.deployments.refresh') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-refresh"></i> Opdatér status</button>
            </form>
        </div>
    </div>
    <div class="box-body table-responsive">
        <table class="table table-striped">
            <thead><tr><th>VM</th><th>ID</th><th>OS</th><th>Proxmox</th><th>Status</th><th>Oprettet</th></tr></thead>
            <tbody>
                @forelse($deployments as $job)
                    @php
                        $stateLabels = [
                            'reserved' => 'Afventer',
                            'clone_requesting' => 'Sender kloning',
                            'cloning' => 'Kloner...',
                            'ready' => 'Klar / afventer start',
                            'start_requesting' => 'Sender opstart',
                            'starting' => 'Starter...',
                            'running' => 'Startet',
                            'failed' => 'Fejlet',
                            'unknown' => 'Kontrollér Proxmox',
                        ];
                        $stateClass = $job->status === 'running' ? 'label-success' : ($job->status === 'failed' || $job->status === 'unknown' ? 'label-danger' : 'label-info');
                    @endphp
                    <tr>
                        <td><strong>{{ $job->name }}</strong><small class="pve-vm-small">{{ $job->node }}</small></td>
                        <td>{{ $job->vmid }}</td>
                        <td>{{ $presets[$job->preset_key]['name'] ?? $job->preset_key }}</td>
                        <td>{{ $job->connection_name ?: 'Frakoblet' }}</td>
                        <td><span class="label {{ $stateClass }}">{{ $stateLabels[$job->status] ?? $job->status }}</span>
                            @if($job->last_error)<small class="pve-vm-small text-danger">{{ $job->last_error }}</small>@endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($job->created_at)->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Du har endnu ikke startet en VM fra Nodexa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

<style>
.pve-vm-intro{display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;background:#101e2e;border:1px solid #26425d;border-radius:12px;padding:22px 24px;margin-bottom:25px}
.pve-vm-intro h2{margin:4px 0 8px;color:#f0f6ff;font-size:25px;font-weight:750}
.pve-vm-intro p{color:#a1b7cf;margin:0;max-width:770px;line-height:1.6}
.pve-vm-label{font-size:10px;font-weight:800;letter-spacing:.11em;color:#83b4e9}
.pve-vm-section-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:15px}
.pve-vm-section-header h3{margin:4px 0 0;font-size:19px}
.pve-vm-count{font-size:11px;color:#b6c5d6;background:#14283e;padding:7px 10px;border:1px solid #29445f;border-radius:9px}
.pve-vm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-bottom:24px}
.pve-vm-card{background:#0a1827;border:1px solid #29435e;border-radius:11px;padding:18px;min-width:0;display:flex;flex-direction:column;gap:9px}
.pve-vm-card-head{display:flex;align-items:center;justify-content:space-between}
.pve-vm-icon{width:43px;height:43px;background:#34281d;color:#e2a36a;border-radius:10px;display:grid;place-items:center;font-size:23px}
.pve-vm-icon.is-windows{background:#102f50;color:#81c1ff}
.pve-vm-tag{border-radius:8px;font-size:10px;font-weight:750;padding:5px 8px;background:#183149;color:#accdeb}
.pve-vm-card h4{font-size:15px;font-weight:750;margin:3px 0 0;color:#edf6ff}
.pve-vm-card>p{font-size:12px;color:#9ab0c7;line-height:1.5;min-height:36px}
.pve-vm-not-ready{font-size:11px;border:1px dashed #375068;border-radius:9px;padding:12px;color:#8da5c0;margin-top:auto}
.pve-vm-source{display:flex;gap:7px;justify-content:space-between;align-items:center;background:#14283c;border:1px solid #2d4a66;border-radius:7px;padding:8px 9px;font-size:11px;color:#a8c6e6}
.pve-vm-source span{overflow-wrap:anywhere}
.pve-vm-source form{flex-shrink:0}
.pve-vm-remove{border:0;background:none;color:#d08b9b;cursor:pointer;padding:5px}
.pve-vm-launch{display:grid;gap:8px;margin-top:5px;border-top:1px solid #263f57;padding-top:13px}
.pve-vm-launch label{font-size:11px;margin-bottom:0;color:#bad0e6}
.pve-vm-setup .help-block,.pve-vm-help p,.pve-vm-help li{font-size:12px;line-height:1.6}
.pve-vm-help li{margin-bottom:9px}
.pve-vm-jobs{margin-top:14px}
.pve-vm-small{display:block;font-size:11px;color:#8097b0;margin-top:4px;line-height:1.4}
@media(max-width:900px){.pve-vm-grid{grid-template-columns:minmax(0,1fr)}}
@media(max-width:767px){.pve-vm-intro{padding:17px}.pve-vm-intro h2{font-size:22px}.pve-vm-grid{grid-template-columns:minmax(0,1fr)}}
</style>
@endsection
