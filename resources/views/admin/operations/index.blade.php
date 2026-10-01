@extends('layouts.admin')
@section('title','Operations Center')
@section('content-header')
<h1>Operations Center <small>Status, incidents, health, billing, backups, affiliates, teams og automation.</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Operations Center</li></ol>
@endsection

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-heartbeat"></i> Status Components</h3><a class="btn btn-xs btn-default pull-right" href="{{ route('store.status') }}" target="_blank">Åbn offentlig status</a></div>
            <div class="box-body">
                <form method="POST" action="{{ route('admin.operations.components.store') }}">@csrf
                    <div class="row">
                        <div class="col-sm-3"><div class="form-group"><label>Navn</label><input class="form-control" name="name" required placeholder="Panel"></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="operational">Operational</option><option value="degraded">Degraded</option><option value="partial_outage">Partial outage</option><option value="major_outage">Major outage</option><option value="maintenance">Maintenance</option></select></div></div>
                        <div class="col-sm-4"><div class="form-group"><label>Beskrivelse</label><input class="form-control" name="description"></div></div>
                        <div class="col-sm-2"><div class="form-group"><label>Sortering</label><input class="form-control" type="number" name="sort_order" value="0"></div></div>
                    </div>
                    <label><input type="checkbox" name="is_public" value="1" checked> Offentlig</label>
                    <button class="btn btn-primary btn-sm pull-right">Opret komponent</button>
                </form>
            </div>
            <div class="box-body no-padding table-responsive">
                <table class="table"><tr><th>Komponent</th><th>Status</th><th>Beskrivelse</th><th></th></tr>
                @foreach($components as $component)
                <tr>
                    <td><strong>{{ $component->name }}</strong></td>
                    <td colspan="2">
                        <form method="POST" action="{{ route('admin.operations.components.update',$component->id) }}" class="form-inline">@csrf @method('PATCH')
                            <select name="status" class="form-control input-sm">@foreach(['operational','degraded','partial_outage','major_outage','maintenance'] as $s)<option value="{{ $s }}" {{ $component->status===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select>
                            <input class="form-control input-sm" style="min-width:250px" name="description" value="{{ $component->description }}">
                            <label><input type="checkbox" name="is_public" value="1" {{ $component->is_public?'checked':'' }}> Public</label>
                            <button class="btn btn-xs btn-primary">Gem</button>
                        </form>
                    </td>
                    <td class="text-right"><form method="POST" action="{{ route('admin.operations.components.delete',$component->id) }}">@csrf @method('DELETE')<button class="btn btn-xs btn-danger" onclick="return confirm('Slet komponent?')">Slet</button></form></td>
                </tr>
                @endforeach
                </table>
            </div>
        </div>

        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Incidents</h3></div>
            <div class="box-body">
                <form method="POST" action="{{ route('admin.operations.incidents.store') }}">@csrf
                    <div class="row">
                        <div class="col-sm-5"><div class="form-group"><label>Titel</label><input class="form-control" name="title" required placeholder="Problemer med DK-01"></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Severity</label><select class="form-control" name="severity"><option value="minor">Minor</option><option value="major">Major</option><option value="critical">Critical</option><option value="maintenance">Maintenance</option><option value="info">Info</option></select></div></div>
                        <div class="col-sm-4"><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="investigating">Investigating</option><option value="identified">Identified</option><option value="monitoring">Monitoring</option><option value="resolved">Resolved</option></select></div></div>
                    </div>
                    <div class="form-group"><label>Besked</label><textarea class="form-control" name="message" rows="3" required></textarea></div>
                    <label><input type="checkbox" name="published" value="1" checked> Publicér</label>
                    <button class="btn btn-warning btn-sm pull-right">Opret incident</button>
                </form>
            </div>
            <div class="box-body">
                @forelse($incidents as $incident)
                <div style="padding:12px 0;border-top:1px solid rgba(128,128,128,.18)">
                    <div style="display:flex;justify-content:space-between;gap:15px;align-items:flex-start"><div><strong>{{ $incident->title }}</strong><br><small class="text-muted">{{ $incident->severity }} · {{ $incident->status }} · {{ $incident->started_at }}</small></div><span class="label {{ $incident->resolved_at?'label-success':'label-warning' }}">{{ $incident->resolved_at?'Resolved':'Active' }}</span></div>
                    <p style="margin:8px 0">{{ $incident->message }}</p>
                    <form method="POST" action="{{ route('admin.operations.incidents.update',$incident->id) }}">@csrf
                        <div class="row"><div class="col-sm-3"><select class="form-control input-sm" name="status">@foreach(['investigating','identified','monitoring','resolved'] as $s)<option value="{{ $s }}" {{ $incident->status===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select></div><div class="col-sm-7"><input class="form-control input-sm" name="message" required placeholder="Statusopdatering..."></div><div class="col-sm-2"><button class="btn btn-sm btn-default btn-block">Opdater</button></div></div>
                    </form>
                    @if(($incidentUpdates[$incident->id] ?? collect())->isNotEmpty())<div style="margin-top:8px">@foreach(($incidentUpdates[$incident->id] ?? collect())->take(3) as $update)<small class="text-muted"><strong>{{ $update->status }}</strong> · {{ $update->message }} · {{ $update->created_at }}</small><br>@endforeach</div>@endif
                </div>
                @empty<p class="text-muted">Ingen incidents endnu.</p>@endforelse
            </div>
        </div>

        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-credit-card"></i> Billing & Subscriptions</h3><form method="POST" action="{{ route('admin.operations.subscriptions.sync') }}" style="display:inline" class="pull-right">@csrf<button class="btn btn-xs btn-default">Synk abonnementer fra aktive ordrer</button></form></div>
            <div class="box-body table-responsive">
                <table class="table"><tr><th>Faktura</th><th>Kunde</th><th>Beløb</th><th>Status</th><th>Forfalder</th><th></th></tr>
                @foreach($invoices as $invoice)<tr><td><strong>{{ $invoice->number }}</strong></td><td>{{ $invoice->user_email }}</td><td>{{ number_format($invoice->total,2,',','.') }} {{ $invoice->currency }}</td><td><span class="label {{ $invoice->status==='paid'?'label-success':($invoice->status==='overdue'?'label-danger':'label-warning') }}">{{ $invoice->status }}</span></td><td>{{ $invoice->due_at }}</td><td>@if($invoice->status!=='paid')<form method="POST" action="{{ route('admin.operations.invoices.paid',$invoice->id) }}">@csrf<input type="hidden" name="payment_method" value="manual"><button class="btn btn-xs btn-success">Markér betalt</button></form>@endif</td></tr>@endforeach
                </table>
            </div>
            <div class="box-body"><h4>Abonnementer</h4><div class="table-responsive"><table class="table table-condensed"><tr><th>Kunde</th><th>Server</th><th>Beløb</th><th>Næste faktura</th><th>Status</th></tr>@foreach($subscriptions as $subscription)<tr><td>{{ $subscription->user_email }}</td><td>{{ $subscription->server_name ?: '—' }}</td><td>{{ number_format($subscription->amount,2,',','.') }} {{ $subscription->currency }}</td><td>{{ $subscription->next_invoice_at }}</td><td>{{ $subscription->status }}</td></tr>@endforeach</table></div></div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-cogs"></i> Automation</h3></div>
            <div class="box-body"><p class="text-muted">Kører automatisk hvert 5. minut via Laravel scheduler: node health, backup policies, fakturering og overdue suspension.</p><form method="POST" action="{{ route('admin.operations.automation') }}">@csrf<button class="btn btn-primary btn-block"><i class="fa fa-play"></i> Kør nu</button></form></div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-server"></i> Node Health</h3></div>
            <div class="box-body no-padding"><table class="table table-condensed"><tr><th>Node</th><th>Status</th><th>Latency</th></tr>@forelse($health as $check)<tr><td>{{ $check->node_name }}<br><small>{{ $check->fqdn }}</small></td><td><span class="label {{ $check->status==='online'?'label-success':'label-danger' }}">{{ $check->status }}</span></td><td>{{ $check->latency_ms }} ms</td></tr>@empty<tr><td colspan="3" class="text-muted">Ingen health checks endnu.</td></tr>@endforelse</table></div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus-square"></i> Service Add-ons</h3></div>
            <form method="POST" action="{{ route('admin.operations.addons.store') }}">@csrf<div class="box-body">
                <div class="form-group"><label>Navn</label><input class="form-control" name="name" required placeholder="+2 GB RAM"></div>
                <div class="form-group"><label>Beskrivelse</label><input class="form-control" name="description"></div>
                <div class="form-group"><label>Pris/md DKK</label><input class="form-control" type="number" step=".01" name="price_monthly" required></div>
                <div class="row"><div class="col-xs-6"><label>RAM MB</label><input class="form-control" type="number" name="memory_delta" value="0"></div><div class="col-xs-6"><label>Disk MB</label><input class="form-control" type="number" name="disk_delta" value="0"></div></div>
                <div class="row" style="margin-top:8px"><div class="col-xs-6"><label>CPU %</label><input class="form-control" type="number" name="cpu_delta" value="0"></div><div class="col-xs-6"><label>Backups</label><input class="form-control" type="number" name="backup_delta" value="0"></div></div>
                <label style="margin-top:10px"><input type="checkbox" name="enabled" value="1" checked> Aktiv</label>
            </div><div class="box-footer"><button class="btn btn-info btn-block">Opret add-on</button></div></form>
            <div class="box-body no-padding"><table class="table table-condensed">@foreach($addons as $addon)<tr><td><strong>{{ $addon->name }}</strong><br><small>{{ number_format($addon->price_monthly,2,',','.') }} DKK/md</small></td><td class="text-right"><small>RAM +{{ $addon->memory_delta }} · CPU +{{ $addon->cpu_delta }} · Disk +{{ $addon->disk_delta }}</small></td></tr>@endforeach</table></div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-users"></i> Affiliates</h3></div>
            <div class="box-body no-padding"><table class="table table-condensed"><tr><th>Kunde</th><th>Saldo</th><th></th></tr>@foreach($affiliates as $affiliate)<tr><td>{{ $affiliate->user_email }}<br><small>{{ $affiliate->code }} · {{ $affiliate->clicks }} klik</small></td><td>{{ number_format($affiliate->balance,2,',','.') }}</td><td>@if($affiliate->balance>0)<form method="POST" action="{{ route('admin.operations.affiliates.payout',$affiliate->id) }}">@csrf<button class="btn btn-xs btn-default">Udbetalt</button></form>@endif</td></tr>@endforeach</table></div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-building"></i> Teams</h3></div>
            <div class="box-body no-padding"><table class="table table-condensed">@forelse($organizations as $org)<tr><td><strong>{{ $org->name }}</strong><br><small>{{ $org->owner_email }}</small></td></tr>@empty<tr><td class="text-muted">Ingen organisationer endnu.</td></tr>@endforelse</table></div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plug"></i> Webhooks</h3></div>
            <div class="box-body no-padding"><table class="table table-condensed">@forelse($webhooks as $webhook)<tr><td><strong>{{ $webhook->name }}</strong><br><small>{{ $webhook->last_status ?: 'aldrig kørt' }}</small></td></tr>@empty<tr><td class="text-muted">Ingen webhooks endnu.</td></tr>@endforelse</table></div>
        </div>
    </div>
</div>

<div class="box">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Audit Log</h3></div>
    <div class="box-body table-responsive"><table class="table table-hover"><tr><th>Tid</th><th>Bruger</th><th>Area</th><th>Action</th><th>Target</th><th>IP</th></tr>@forelse($audit as $entry)<tr><td>{{ $entry->created_at }}</td><td>{{ $entry->user_email ?: 'System' }}</td><td>{{ $entry->area }}</td><td><strong>{{ $entry->action }}</strong><br><small>{{ $entry->description }}</small></td><td>{{ $entry->target_type }} {{ $entry->target_id }}</td><td>{{ $entry->ip }}</td></tr>@empty<tr><td colspan="6" class="text-muted">Ingen audit entries endnu.</td></tr>@endforelse</table></div>
</div>
@endsection