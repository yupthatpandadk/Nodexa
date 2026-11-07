@extends('store.layout')
@section('title', 'Support Tickets')
@section('content')
@include('store.client.tickets.alerts')
<div class="container tickets"><div class="ticketHead"><div><div class="eyebrow">Client Area / Support</div><h1>Supporttickets</h1><p class="muted">Dine samtaler med Nodexa Support samlet ét sted.</p></div><a class="btn primary" href="{{ route('store.client.tickets.create') }}">Opret ticket</a></div>
<form class="ticketTools" method="GET"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Søg efter emne eller ticketnummer" aria-label="Søg tickets"><select class="input" name="status" aria-label="Status"><option value="">Alle statusser</option>@foreach(['open'=>'Åben','customer_reply'=>'Kundesvar','waiting_customer'=>'Afventer dig','answered'=>'Besvaret','closed'=>'Lukket'] as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select><button class="btn">Søg</button></form>
@forelse($tickets as $t)<a class="ticketRow" href="{{ route('store.client.tickets.show', $t) }}"><div><b>{{ $t->subject }}</b><small>{{ $t->ticket_number }} · {{ $t->department }} @if($t->server) · {{ $t->server->name }} @endif</small></div><span>{{ ucfirst($t->priority) }}</span><span class="statusBadge">{{ str_replace('_', ' ', $t->status) }}{{ $t->merged_into_id ? ' · Sammenlagt' : '' }}</span></a>@empty<div class="card"><h2>Ingen tickets</h2><p class="muted">Opret en supportsag, hvis du har brug for hjælp, eller prøv en anden søgning.</p></div>@endforelse
<div class="ticketPagination">{{ $tickets->links() }}</div>
</div>
@include('store.client.tickets.styles')
@endsection
