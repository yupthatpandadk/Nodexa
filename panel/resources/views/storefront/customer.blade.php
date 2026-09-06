@extends('storefront.layout')

@section('title', 'Kundeområde')
@section('description', 'Nodexa kundeområde med services, fakturaer, support og konto.')

@section('content')
@php
    $panelOrigin = rtrim((string) config('app.url'), '/');
    $clientBase = $panelOrigin . '/store/client';
    $money = static fn ($value, $currency = 'DKK') => number_format((float) $value, 2, ',', '.') . ' ' . strtoupper((string) $currency);
    $statusLabel = [
        'open' => 'Åben', 'answered' => 'Besvaret', 'customer_reply' => 'Kundesvar', 'closed' => 'Lukket',
        'draft' => 'Kladde', 'unpaid' => 'Ubetalt', 'paid' => 'Betalt', 'overdue' => 'Forfalden', 'cancelled' => 'Annulleret', 'refunded' => 'Refunderet',
    ];
@endphp

<style>
    .nx-client-shell{max-width:1240px;margin:0 auto;padding:42px 22px 72px}.nx-client-head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:24px}.nx-client-head h1{margin:0;font-size:clamp(30px,5vw,46px)}.nx-client-head p{margin:8px 0 0;color:var(--nodexa-muted)}.nx-client-grid{display:grid;grid-template-columns:250px minmax(0,1fr);gap:22px}.nx-client-nav,.nx-client-card,.nx-stat{border:1px solid var(--nodexa-border);background:linear-gradient(145deg,rgba(var(--nodexa-accent-rgb),.055),rgba(var(--nodexa-accent-rgb),.012)),var(--nodexa-surface);box-shadow:0 18px 44px rgba(0,0,0,.18);border-radius:18px}.nx-client-nav{padding:12px;height:max-content;position:sticky;top:92px}.nx-client-nav a{display:flex;gap:10px;align-items:center;padding:12px 13px;border-radius:12px;color:var(--nodexa-muted);text-decoration:none;font-weight:650;margin:3px 0}.nx-client-nav a.active,.nx-client-nav a:hover{color:#fff;background:var(--nodexa-accent-soft);border:1px solid var(--nodexa-border)}.nx-client-content{min-width:0}.nx-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.nx-stat{padding:18px}.nx-stat small{display:block;color:var(--nodexa-muted);text-transform:uppercase;letter-spacing:.08em;font-weight:700}.nx-stat strong{font-size:30px;display:block;margin-top:7px}.nx-client-card{padding:22px;margin-bottom:18px}.nx-client-card h2{margin:0 0 4px}.nx-client-card>.muted,.muted{color:var(--nodexa-muted)}.nx-table-wrap{overflow:auto;margin-top:16px}.nx-client-table{width:100%;border-collapse:collapse;min-width:650px}.nx-client-table th,.nx-client-table td{text-align:left;padding:13px 12px;border-bottom:1px solid var(--nodexa-border)}.nx-client-table th{font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--nodexa-muted)}.nx-badge{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:750;background:var(--nodexa-accent-soft);border:1px solid var(--nodexa-border)}.nx-service-list{display:grid;gap:12px;margin-top:16px}.nx-service{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:15px;border:1px solid var(--nodexa-border);border-radius:14px;background:rgba(var(--nodexa-accent-rgb),.035)}.nx-service strong{display:block}.nx-service small{color:var(--nodexa-muted)}.nx-empty{text-align:center;padding:38px 18px;border:1px dashed var(--nodexa-border-strong);border-radius:14px;margin-top:16px;color:var(--nodexa-muted)}.nx-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.nx-profile{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:16px}.nx-profile div{padding:14px;border:1px solid var(--nodexa-border);border-radius:12px}.nx-profile small{display:block;color:var(--nodexa-muted);margin-bottom:5px}@media(max-width:900px){.nx-client-grid{grid-template-columns:1fr}.nx-client-nav{position:static;display:grid;grid-template-columns:repeat(2,1fr)}.nx-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.nx-client-shell{padding:28px 14px 58px}.nx-client-head{align-items:flex-start;flex-direction:column}.nx-client-nav{grid-template-columns:1fr}.nx-stats{grid-template-columns:1fr 1fr}.nx-profile{grid-template-columns:1fr}.nx-service{align-items:flex-start;flex-direction:column}}
</style>

<section class="nx-client-shell">
    <div class="nx-client-head">
        <div>
            <span class="nx-kicker">KUNDECENTER</span>
            <h1>Hej, {{ $user->name_first ?: $user->username }}</h1>
            <p>Administrér hosting, fakturaer, support og din konto samlet ét sted.</p>
        </div>
        <a class="nx-btn nx-btn-primary" href="{{ $panelOrigin }}/">Åbn serverpanel →</a>
    </div>

    <div class="nx-client-grid">
        <aside class="nx-client-nav">
            <a class="{{ $section === 'dashboard' ? 'active' : '' }}" href="{{ $clientBase }}">⌂ Dashboard</a>
            <a class="{{ $section === 'services' ? 'active' : '' }}" href="{{ $clientBase }}/services">▣ Mine services</a>
            <a class="{{ $section === 'invoices' ? 'active' : '' }}" href="{{ $clientBase }}/invoices">▤ Fakturaer</a>
            <a class="{{ $section === 'support' ? 'active' : '' }}" href="{{ $clientBase }}/support">🎫 Support</a>
            <a class="{{ $section === 'account' ? 'active' : '' }}" href="{{ $clientBase }}/account">⚙ Konto</a>
        </aside>

        <div class="nx-client-content">
            @if($section === 'dashboard')
                <div class="nx-stats">
                    <div class="nx-stat"><small>Services</small><strong>{{ $stats['services'] }}</strong></div>
                    <div class="nx-stat"><small>Aktive</small><strong>{{ $stats['active_services'] }}</strong></div>
                    <div class="nx-stat"><small>Åbne tickets</small><strong>{{ $stats['open_tickets'] }}</strong></div>
                    <div class="nx-stat"><small>Ubetalte</small><strong>{{ $stats['unpaid_invoices'] }}</strong></div>
                </div>

                <div class="nx-client-card">
                    <h2>Mine services</h2><p class="muted">Dine aktive Nodexa-servere.</p>
                    @if($services->isEmpty())
                        <div class="nx-empty">Du har ingen services endnu.</div>
                    @else
                        <div class="nx-service-list">
                            @foreach($services->take(5) as $service)
                                <div class="nx-service">
                                    <div><strong>{{ $service->name }}</strong><small>{{ $service->memory }} MB RAM · {{ $service->disk }} MB disk · {{ $service->cpu }}% CPU</small></div>
                                    <div><span class="nx-badge">{{ $service->status === 'suspended' ? 'Suspenderet' : 'Aktiv' }}</span> <a class="nx-btn nx-btn-ghost" href="{{ $panelOrigin }}/server/{{ $service->uuidShort }}">Administrér</a></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="nx-actions"><a class="nx-btn nx-btn-ghost" href="{{ $clientBase }}/services">Se alle services</a><a class="nx-btn nx-btn-primary" href="{{ route('storefront.pricing') }}">Bestil ny service</a></div>
                </div>

                <div class="nx-client-card">
                    <h2>Seneste support</h2><p class="muted">Tickets og seneste status.</p>
                    @if($tickets->isEmpty())
                        <div class="nx-empty">Ingen tickets endnu.</div>
                    @else
                        <div class="nx-table-wrap"><table class="nx-client-table"><thead><tr><th>#</th><th>Emne</th><th>Prioritet</th><th>Status</th><th>Opdateret</th></tr></thead><tbody>
                        @foreach($tickets->take(5) as $ticket)<tr><td>#{{ $ticket->id }}</td><td>{{ $ticket->subject }}</td><td>{{ ucfirst($ticket->priority) }}</td><td><span class="nx-badge">{{ $statusLabel[$ticket->status] ?? $ticket->status }}</span></td><td>{{ optional(\Carbon\Carbon::parse($ticket->last_reply_at ?: $ticket->updated_at))->format('d/m/Y H:i') }}</td></tr>@endforeach
                        </tbody></table></div>
                    @endif
                    <div class="nx-actions"><a class="nx-btn nx-btn-primary" href="{{ $panelOrigin }}/tickets">Åbn supportcenter</a></div>
                </div>
            @elseif($section === 'services')
                <div class="nx-client-card"><h2>Mine services</h2><p class="muted">Alle servere tilknyttet din konto.</p>
                    @if($services->isEmpty())<div class="nx-empty">Du har ingen services endnu.</div>@else<div class="nx-service-list">@foreach($services as $service)<div class="nx-service"><div><strong>{{ $service->name }}</strong><small>ID {{ $service->uuidShort }} · {{ $service->memory }} MB RAM · {{ $service->disk }} MB disk · {{ $service->cpu }}% CPU</small></div><div><span class="nx-badge">{{ $service->status === 'suspended' ? 'Suspenderet' : 'Aktiv' }}</span> <a class="nx-btn nx-btn-primary" href="{{ $panelOrigin }}/server/{{ $service->uuidShort }}">Administrér</a></div></div>@endforeach</div>@endif
                    <div class="nx-actions"><a class="nx-btn nx-btn-primary" href="{{ route('storefront.pricing') }}">Bestil ny service</a></div>
                </div>
            @elseif($section === 'invoices')
                <div class="nx-client-card"><h2>Fakturaer</h2><p class="muted">Se betalinger og kommende forfald.</p>
                    @if($invoices->isEmpty())<div class="nx-empty">Der er ingen fakturaer på din konto endnu.</div>@else<div class="nx-table-wrap"><table class="nx-client-table"><thead><tr><th>Faktura</th><th>Beskrivelse</th><th>Beløb</th><th>Forfald</th><th>Status</th></tr></thead><tbody>@foreach($invoices as $invoice)<tr><td>{{ $invoice->number }}</td><td>{{ $invoice->description ?: 'Nodexa service' }}</td><td>{{ $money($invoice->total, $invoice->currency) }}</td><td>{{ $invoice->due_at ? \Carbon\Carbon::parse($invoice->due_at)->format('d/m/Y') : '—' }}</td><td><span class="nx-badge">{{ $statusLabel[$invoice->status] ?? $invoice->status }}</span></td></tr>@endforeach</tbody></table></div>@endif
                </div>
            @elseif($section === 'support')
                <div class="nx-client-card"><h2>Support</h2><p class="muted">Få hjælp og følg dine eksisterende tickets.</p>
                    @if($tickets->isEmpty())<div class="nx-empty">Du har ingen tickets.</div>@else<div class="nx-table-wrap"><table class="nx-client-table"><thead><tr><th>#</th><th>Emne</th><th>Kategori</th><th>Prioritet</th><th>Status</th></tr></thead><tbody>@foreach($tickets as $ticket)<tr><td>#{{ $ticket->id }}</td><td>{{ $ticket->subject }}</td><td>{{ ucfirst($ticket->category) }}</td><td>{{ ucfirst($ticket->priority) }}</td><td><span class="nx-badge">{{ $statusLabel[$ticket->status] ?? $ticket->status }}</span></td></tr>@endforeach</tbody></table></div>@endif
                    <div class="nx-actions"><a class="nx-btn nx-btn-primary" href="{{ $panelOrigin }}/tickets">Opret / administrér ticket</a></div>
                </div>
            @else
                <div class="nx-client-card"><h2>Min konto</h2><p class="muted">Kontooplysninger for din Nodexa-konto.</p><div class="nx-profile"><div><small>Navn</small><strong>{{ trim($user->name_first . ' ' . $user->name_last) ?: $user->username }}</strong></div><div><small>Brugernavn</small><strong>{{ $user->username }}</strong></div><div><small>E-mail</small><strong>{{ $user->email }}</strong></div><div><small>Kunde-ID</small><strong>#{{ $user->id }}</strong></div></div><div class="nx-actions"><a class="nx-btn nx-btn-primary" href="{{ $panelOrigin }}/account">Redigér konto</a></div></div>
            @endif
        </div>
    </div>
</section>
@endsection
