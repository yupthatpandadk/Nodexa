@extends('layouts.admin')
@section('title','Ticket Center')
@section('content-header')<h1>Ticket Center <small>Kundesupport og henvendelser</small></h1>@endsection
@section('content')
<style>
.ticket-stat{background:#fff;border-radius:7px;padding:18px;border-left:4px solid #3c8dbc;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:15px}.ticket-stat strong{font-size:27px;display:block}.ticket-stat span{color:#777}.ticket-row{cursor:pointer}.ticket-subject{font-weight:600}.ticket-meta{font-size:12px;color:#888}.priority-high{color:#dd4b39;font-weight:700}.priority-normal{color:#f39c12}.priority-low{color:#00a65a}.ticket-filters{padding:15px;background:#fff;border-radius:6px;margin-bottom:18px}.ticket-empty{padding:45px;text-align:center;color:#999}
</style>
<div class="row">
 <div class="col-sm-3"><div class="ticket-stat"><strong>{{ $stats['waiting'] ?? 0 }}</strong><span>Afventer medarbejder</span></div></div>
 <div class="col-sm-3"><div class="ticket-stat"><strong>{{ $stats['open'] ?? 0 }}</strong><span>Åbne tickets</span></div></div>
 <div class="col-sm-3"><div class="ticket-stat"><strong>{{ $stats['answered'] ?? 0 }}</strong><span>Besvaret</span></div></div>
 <div class="col-sm-3"><div class="ticket-stat"><strong>{{ $stats['closed'] ?? 0 }}</strong><span>Lukkede</span></div></div>
</div>
<form method="GET" class="ticket-filters"><div class="row">
 <div class="col-md-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Søg efter ticket, kunde, e-mail eller emne..."></div>
 <div class="col-md-2"><select class="form-control" name="status"><option value="">Alle statusser</option>@foreach(['open'=>'Åben','customer_reply'=>'Kundesvar','answered'=>'Besvaret','closed'=>'Lukket'] as $v=>$n)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $n }}</option>@endforeach</select></div>
 <div class="col-md-2"><select class="form-control" name="department"><option value="">Alle afdelinger</option>@foreach(['support'=>'Support','billing'=>'Fakturering','sales'=>'Salg'] as $v=>$n)<option value="{{ $v }}" @selected(request('department')===$v)>{{ $n }}</option>@endforeach</select></div>
 <div class="col-md-2"><select class="form-control" name="priority"><option value="">Alle prioriteter</option>@foreach(['high'=>'Høj','normal'=>'Normal','low'=>'Lav'] as $v=>$n)<option value="{{ $v }}" @selected(request('priority')===$v)>{{ $n }}</option>@endforeach</select></div>
 <div class="col-md-2"><button class="btn btn-primary btn-block"><i class="fa fa-search"></i> Filtrer</button></div>
</div></form>
<div class="box box-primary"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-ticket"></i> Support tickets</h3><span class="badge pull-right">{{ $tickets->count() }}</span></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>#</th><th>Kunde</th><th>Emne</th><th>Afdeling</th><th>Prioritet</th><th>Status</th><th>Seneste aktivitet</th><th></th></tr></thead><tbody>
@forelse($tickets as $ticket)
<tr class="ticket-row" onclick="window.location='{{ route('admin.tickets.show',$ticket->id) }}'">
<td><strong>#{{ $ticket->id }}</strong></td><td>{{ $ticket->username ?: 'Kunde #'.$ticket->user_id }}<div class="ticket-meta">{{ $ticket->email }}</div></td><td class="ticket-subject">{{ $ticket->subject }}</td><td>{{ ['support'=>'Support','billing'=>'Fakturering','sales'=>'Salg'][$ticket->department] ?? ucfirst($ticket->department) }}</td><td class="priority-{{ $ticket->priority }}">{{ ['high'=>'Høj','normal'=>'Normal','low'=>'Lav'][$ticket->priority] ?? ucfirst($ticket->priority) }}</td><td>@php($labels=['open'=>['primary','Åben'],'customer_reply'=>['danger','Kundesvar'],'answered'=>['success','Besvaret'],'closed'=>['default','Lukket']]) @php($s=$labels[$ticket->status]??['warning',$ticket->status])<span class="label label-{{ $s[0] }}">{{ $s[1] }}</span></td><td>{{ \Carbon\Carbon::parse($ticket->updated_at)->diffForHumans() }}</td><td><a href="{{ route('admin.tickets.show',$ticket->id) }}" class="btn btn-xs btn-default">Åbn</a></td>
</tr>@empty<tr><td colspan="8"><div class="ticket-empty"><i class="fa fa-inbox fa-3x"></i><h4>Ingen tickets fundet</h4><p>Der er ingen tickets der matcher filtrene.</p></div></td></tr>@endforelse
</tbody></table></div></div>
@endsection