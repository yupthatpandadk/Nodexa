@extends('layouts.admin')

@section('title')
    Tickets
@endsection

@section('content-header')
    <h1>Tickets<small>Administrer supporthenvendelser fra brugerne.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Tickets</li>
    </ol>
@endsection

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Ticketliste</h3></div>
            <div class="box-body">
                <form method="GET" action="{{ route('admin.tickets') }}" style="margin-bottom:12px;">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">Alle statusser</option>
                        @foreach(['open'=>'Åben','customer_reply'=>'Kundesvar','answered'=>'Besvaret','closed'=>'Lukket'] as $value=>$label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
                @forelse($tickets as $ticket)
                    <a href="{{ route('admin.tickets', ['ticket' => $ticket->id, 'status' => request('status')]) }}" style="display:block;padding:11px;border:1px solid var(--nodexa-border);border-radius:10px;margin-bottom:8px;{{ $selected && $selected->id === $ticket->id ? 'background:var(--nodexa-accent-soft);border-color:var(--nodexa-accent);' : '' }}">
                        <strong>#{{ $ticket->id }} · {{ $ticket->subject }}</strong><br>
                        <small>{{ $ticket->username }} · {{ $ticket->priority }} · {{ $ticket->status }}</small>
                    </a>
                @empty
                    <span class="text-muted">Ingen tickets fundet.</span>
                @endforelse
            </div>
            @if($tickets->hasPages())<div class="box-footer text-center">{{ $tickets->appends(request()->query())->links() }}</div>@endif
        </div>
    </div>

    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ $selected ? '#'.$selected->id.' · '.$selected->subject : 'Vælg en ticket' }}</h3>
            </div>
            <div class="box-body">
                @if($selected)
                    <div class="row" style="margin-bottom:14px;">
                        <div class="col-md-7">
                            <strong>Kunde:</strong> {{ $selected->username }} ({{ $selected->email }})<br>
                            <strong>Kategori:</strong> {{ $selected->category }}<br>
                            <strong>Tildelt:</strong> {{ $selected->assigned_username ?: 'Ingen' }}
                        </div>
                        <div class="col-md-5">
                            <form method="POST" action="{{ route('admin.tickets.update', $selected->id) }}">
                                {!! csrf_field() !!}{!! method_field('PATCH') !!}
                                <div class="row">
                                    <div class="col-xs-6"><label>Status<select name="status" class="form-control">@foreach(['open'=>'Åben','customer_reply'=>'Kundesvar','answered'=>'Besvaret','closed'=>'Lukket'] as $value=>$label)<option value="{{ $value }}" {{ $selected->status === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label></div>
                                    <div class="col-xs-6"><label>Prioritet<select name="priority" class="form-control">@foreach(['low'=>'Lav','normal'=>'Normal','high'=>'Høj','urgent'=>'Akut'] as $value=>$label)<option value="{{ $value }}" {{ $selected->priority === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label></div>
                                </div>
                                <button class="btn btn-default btn-sm" type="submit">Gem status</button>
                            </form>
                        </div>
                    </div>

                    @foreach($messages as $message)
                        <div style="padding:12px;border:1px solid var(--nodexa-border);border-left:3px solid {{ $message->is_staff ? 'var(--nodexa-accent)' : 'rgba(255,255,255,.25)' }};border-radius:10px;margin-bottom:10px;background:var(--nodexa-surface-2);">
                            <strong>{{ $message->is_staff ? 'Staff · ' : 'Kunde · ' }}{{ $message->username }}</strong>
                            <div class="text-muted small">{{ $message->created_at }}</div>
                            <div style="white-space:pre-wrap;margin-top:8px;">{{ $message->message }}</div>
                        </div>
                    @endforeach

                    @if($selected->status !== 'closed')
                        <form method="POST" action="{{ route('admin.tickets.reply', $selected->id) }}" style="margin-top:14px;">
                            {!! csrf_field() !!}
                            <div class="form-group"><label>Svar til kunden</label><textarea name="message" class="form-control" rows="6" required></textarea></div>
                            <button class="btn btn-primary" type="submit"><i class="fa fa-reply"></i> Send svar</button>
                        </form>
                    @else
                        <div class="alert alert-info" style="margin-bottom:0;">Denne ticket er lukket.</div>
                    @endif
                @else
                    <span class="text-muted">Vælg en ticket fra listen.</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
