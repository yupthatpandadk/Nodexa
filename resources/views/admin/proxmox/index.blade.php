@extends('layouts.admin')
@section('title', 'Proxmox VE')
@section('content-header')
<h1>Proxmox VE <small>Forbind dine egne hypervisorer til Nodexa</small></h1>
<ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Proxmox VE</li>
</ol>
@endsection

@section('content')
<div class="alert" style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;background:#12283e;border:1px solid #2e5678;color:#d6eaff;border-radius:9px;padding:13px 17px">
    <div><strong><i class="fa fa-clone"></i> VM Templates</strong><div style="font-size:12px;color:#a5bfd7;margin-top:4px">Vælg Ubuntu, Debian, Linux eller Windows Server, og opret en ny VM direkte via Nodexa.</div></div>
    <a href="{{ route('admin.proxmox.templates') }}" class="btn btn-primary btn-sm"><i class="fa fa-th-large"></i> Åbn VM Templates →</a>
</div>
@if(session('success'))
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> {{ $errors->first() }}</div>
@endif

@if(!$ready)
    <div class="alert alert-warning">
        <strong>Databaseopdatering mangler.</strong>
        Kør Nodexas normale opdatering, eller <code>php artisan migrate --force</code>, for at aktivere Proxmox-integrationer.
    </div>
@else
<div class="row">
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-plug"></i> Tilslut Proxmox</h3>
            </div>
            <form method="POST" action="{{ route('admin.proxmox.store') }}" autocomplete="off">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="pve-name">Navn på forbindelse</label>
                        <input id="pve-name" class="form-control" type="text" name="name" value="{{ old('name') }}" placeholder="Proxmox – Produktion" maxlength="120" required>
                    </div>
                    <div class="form-group">
                        <label for="pve-endpoint">Proxmox API-adresse</label>
                        <input id="pve-endpoint" class="form-control" type="url" name="endpoint" value="{{ old('endpoint') }}" placeholder="https://pve.domaene.dk:8006" maxlength="255" required>
                        <p class="help-block">Brug HTTPS og port 8006. Du kan også anvende en HTTPS-reverse-proxy på port 443. Private netværksadresser understøttes, hvis Nodexa-serveren kan nå dem.</p>
                    </div>
                    <div class="form-group">
                        <label for="pve-token-id">API-token ID</label>
                        <input id="pve-token-id" class="form-control" type="text" name="token_id" value="{{ old('token_id') }}" placeholder="nodexa@pve!monitoring" maxlength="255" spellcheck="false" required>
                        <p class="help-block">Opret et token under <strong>Datacenter → Permissions → API Tokens</strong>. Giv den tilhørende bruger og tokenet læserettigheder, fx <code>PVEAuditor</code>.</p>
                    </div>
                    <div class="form-group">
                        <label for="pve-token-secret">API-token secret</label>
                        <input id="pve-token-secret" class="form-control" type="password" name="token_secret" autocomplete="new-password" placeholder="Indsæt token-secret" maxlength="5000" required>
                        <p class="help-block"><i class="fa fa-lock"></i> Gemmes krypteret på serveren. Secret vises aldrig igen.</p>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="verify_tls" value="1" {{ old('verify_tls', '1') ? 'checked' : '' }}> Verificér TLS-certifikat (anbefalet)</label>
                    </div>
                    <p class="help-block">Hvis du bruger Proxmox' selvsignerede standardcertifikat, anbefaler vi at opsætte et gyldigt certifikat. Fravælg kun certifikatkontrol, hvis du forstår sikkerhedsrisikoen, og forbindelsen går over et betroet netværk.</p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-save"></i> Gem Proxmox-forbindelse</button>
                </div>
            </form>
        </div>
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-shield"></i> Adgang og sikkerhed</h3></div>
            <div class="box-body">
                <p>Denne integration er kun tilgængelig for Nodexas ejeradministrator. Den påvirker ikke dine eksisterende kunde-VPS'er.</p>
                <p>Brug et dedikeret Proxmox-token med <strong>Privilege Separation</strong> og så få rettigheder som muligt. Undgå at bruge root-token med fulde rettigheder.</p>
                <p class="text-muted" style="margin-bottom:0">Version 1 er <strong>læsebeskyttet</strong>: forbindelsestest, noder og VM/LXC-overblik. Start, stop, oprettelse og sletning kræver særskilt implementering og sikkerhedskontrol.</p>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-server"></i> Forbundne Proxmox-systemer</h3>
                <span class="badge pull-right">{{ $connections->count() }}</span>
            </div>
            <div class="box-body">
                @forelse($connections as $connection)
                    <div class="pve-connection" id="proxmox-{{ $connection->id }}">
                        <div class="pve-connection-heading">
                            <div>
                                <strong>{{ $connection->name }}</strong>
                                <small>{{ $connection->endpoint }}</small>
                            </div>
                            @if(!$connection->enabled)
                                <span class="label label-default">Deaktiveret</span>
                            @elseif($connection->status === 'connected')
                                <span class="label label-success">Forbundet</span>
                            @elseif($connection->status === 'error')
                                <span class="label label-danger">Forbindelsesfejl</span>
                            @else
                                <span class="label label-warning">Ikke testet</span>
                            @endif
                        </div>
                        @if($connection->last_error)
                            <p class="text-danger" style="margin:8px 0">{{ $connection->last_error }}</p>
                        @endif
                        <p class="pve-details">
                            Token: <code>{{ $connection->token_id }}</code><br>
                            TLS-kontrol: <strong>{{ $connection->verify_tls ? 'Aktiveret' : 'Deaktiveret' }}</strong>
                            @if($connection->last_checked_at)
                                · Testet {{ \Carbon\Carbon::parse($connection->last_checked_at)->format('d.m.Y H:i') }}
                            @endif
                        </p>
                        <div class="pve-actions">
                            <form method="POST" action="{{ route('admin.proxmox.sync', $connection->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-info" {{ !$connection->enabled ? 'disabled' : '' }}><i class="fa fa-refresh"></i> Test &amp; synkronisér</button>
                            </form>
                            <form method="POST" action="{{ route('admin.proxmox.delete', $connection->id) }}" onsubmit="return confirm('Fjern Proxmox-forbindelsen fra Nodexa? Virtuelle maskiner på Proxmox påvirkes ikke.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-unlink"></i> Fjern</button>
                            </form>
                        </div>
                        <details class="pve-edit-panel">
                            <summary><i class="fa fa-pencil"></i> Redigér forbindelse</summary>
                            <form method="POST" action="{{ route('admin.proxmox.update', $connection->id) }}" autocomplete="off">
                                @csrf
                                @method('PATCH')
                                <div class="row">
                                    <div class="col-sm-6 form-group">
                                        <label>Navn</label>
                                        <input type="text" class="form-control" name="name" value="{{ $connection->name }}" maxlength="120" required>
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>API URL</label>
                                        <input type="url" class="form-control" name="endpoint" value="{{ $connection->endpoint }}" maxlength="255" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Token ID</label>
                                    <input type="text" class="form-control" name="token_id" value="{{ $connection->token_id }}" maxlength="255" required>
                                </div>
                                <div class="form-group">
                                    <label>Nyt token-secret (valgfrit)</label>
                                    <input type="password" class="form-control" name="token_secret" autocomplete="new-password" placeholder="Tomt felt beholder nuværende secret" maxlength="5000">
                                </div>
                                <div class="checkbox"><label><input type="checkbox" name="verify_tls" value="1" {{ $connection->verify_tls ? 'checked' : '' }}> Verificér TLS</label></div>
                                <div class="checkbox"><label><input type="checkbox" name="enabled" value="1" {{ $connection->enabled ? 'checked' : '' }}> Aktivér forbindelse</label></div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Gem ændringer</button>
                            </form>
                        </details>

                        @if($connection->snapshot_at)
                            <div class="pve-summary">
                                <span><i class="fa fa-sitemap"></i> <strong>{{ count($connection->nodes) }}</strong> noder</span>
                                <span><i class="fa fa-desktop"></i> <strong>{{ count($connection->guests) }}</strong> VM’er / LXC</span>
                                <small>Seneste synkronisering: {{ \Carbon\Carbon::parse($connection->snapshot_at)->format('d.m.Y H:i') }}</small>
                            </div>

                            <div class="pve-data-section">
                                <h4>Noder</h4>
                                <div class="table-responsive">
                                    <table class="table table-condensed table-striped">
                                        <thead><tr><th>Node</th><th>Status</th><th>CPU</th><th>RAM</th></tr></thead>
                                        <tbody>
                                        @forelse($connection->nodes as $node)
                                            <tr>
                                                <td><strong>{{ $node['name'] }}</strong></td>
                                                <td>{{ $node['status'] }}</td>
                                                <td>{{ number_format($node['cpu_percent'], 1, ',', '.') }}%</td>
                                                <td>{{ number_format($node['memory_used']/1073741824, 1, ',', '.') }} / {{ number_format($node['memory_total']/1073741824, 1, ',', '.') }} GB</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted">Ingen synlige noder. Kontrollér tokenets Sys.Audit-rettigheder.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="pve-data-section">
                                <h4>Virtuelle maskiner og containere</h4>
                                <div class="table-responsive pve-guest-table">
                                    <table class="table table-condensed table-striped">
                                        <thead><tr><th>ID</th><th>Navn</th><th>Node</th><th>Type</th><th>Status</th><th>CPU</th><th>RAM</th></tr></thead>
                                        <tbody>
                                        @forelse($connection->guests as $guest)
                                            <tr>
                                                <td>{{ $guest['id'] }}</td>
                                                <td><strong>{{ $guest['name'] }}</strong></td>
                                                <td>{{ $guest['node'] }}</td>
                                                <td>{{ $guest['type'] === 'lxc' ? 'LXC' : 'VM' }}</td>
                                                <td><span class="label {{ $guest['status'] === 'running' ? 'label-success' : 'label-default' }}">{{ $guest['status'] }}</span></td>
                                                <td>{{ number_format($guest['cpu_percent'], 1, ',', '.') }}%</td>
                                                <td>{{ number_format($guest['memory_used']/1073741824, 1, ',', '.') }} / {{ number_format($guest['memory_total']/1073741824, 1, ',', '.') }} GB</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-muted">Ingen VM’er eller LXC-containere synlige. Kontrollér VM.Audit-rettighederne.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <p class="text-muted" style="margin:12px 0 0">Tryk <strong>Test &amp; synkronisér</strong> for at hente noder og VM’er fra Proxmox.</p>
                        @endif
                    </div>
                @empty
                    <div class="pve-empty">
                        <i class="fa fa-cubes fa-2x"></i>
                        <h4>Ingen Proxmox-forbindelser endnu</h4>
                        <p>Udfyld formularen for at tilføje din første Proxmox-server.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endif

<style>
.pve-connection{padding:16px 0;border-bottom:1px solid #314053}
.pve-connection:first-child{padding-top:3px}
.pve-connection:last-child{border-bottom:0}
.pve-connection-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.pve-connection-heading strong,.pve-connection-heading small{display:block;overflow-wrap:anywhere}
.pve-connection-heading strong{font-size:17px}
.pve-connection-heading small{font-size:12px;margin-top:3px;color:#9db1c7}
.pve-details{font-size:12px;color:#a3b7cc;overflow-wrap:anywhere;margin:10px 0}
.pve-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px}
.pve-actions form{margin:0}
.pve-edit-panel{border:1px solid #30445b;border-radius:8px;padding:10px 12px;margin-top:13px}
.pve-edit-panel summary{cursor:pointer;color:#8bbbe9;user-select:none;font-size:12px;font-weight:750}
.pve-edit-panel form{padding-top:14px}
.pve-summary{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 0;margin-top:14px;border-top:1px solid #273c51;font-size:12px}
.pve-summary small{color:#8d9cae}
.pve-data-section{margin-top:15px}
.pve-data-section h4{font-size:14px;font-weight:750;margin:0 0 9px}
.pve-guest-table{max-height:360px;overflow:auto}
.pve-data-section th,.pve-data-section td{font-size:12px;vertical-align:middle!important}
.pve-empty{text-align:center;padding:35px 15px;color:#97adc4}
.pve-empty h4{color:#e3efff}
@media(max-width:767px){.pve-connection-heading{flex-wrap:wrap}.pve-summary{gap:8px}.pve-guest-table{max-height:440px}}
</style>
@endsection
