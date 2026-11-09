@extends('store.layout')
@section('title','Mine VPS’er · Client Area')
@section('content')
<section style="padding:38px 0 80px">
<div class="container">
    <div class="clientHead">
        <div><div class="eyebrow">CLIENT AREA / VPS</div><h1>Mine VPS’er</h1><p class="muted">Dine VPS-servere, samlet og administreret hos Nodexa.</p></div>
        <a class="btn primary" href="{{ route('store.vps') }}">＋ Bestil VPS</a>
    </div>

    @if(session('success'))<div class="notice" style="margin-bottom:16px">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice" style="margin-bottom:16px;border-color:#69333d;color:#ffabb8">{{ $errors->first() }}</div>@endif

    <div class="vpsGrid">
        @forelse($services as $service)
        @php($running = $service->remote_running)
        <article class="vpsCard">
            <div class="vpsTop">
                <div>
                    <div class="miniLabel">{{ \Pterodactyl\Services\Nodexa\CustomerBranding::label($service->product_name) }}</div>
                    <h2><a class="vpsNameLink" href="{{ route('store.client.vps.show', $service->id) }}" aria-label="Åbn kontrolpanel for {{ $service->hostname }}">{{ $service->hostname }}</a></h2>
                    <p>{{ \Pterodactyl\Services\Nodexa\CustomerBranding::label($service->os_label, 'Nodexa VPS') }}</p>
                </div>
                <span class="serviceState {{ $service->status==='active'?'good':($service->status==='suspended'?'bad':'warn') }}">● {{ strtoupper(str_replace('_',' ',$service->status)) }}</span>
            </div>

            <div class="vpsStats">
                <div><span>IP ADDRESS</span><strong>{{ $service->primary_ip ?: 'Afventer' }}</strong></div>
                <div><span>POWER</span><strong>{{ $running === true ? 'Running' : ($running === false ? 'Stopped' : 'Unknown') }}</strong></div>
                <div><span>RESOURCES</span><strong>{{ $service->vcpu ?: '—' }} vCPU · {{ $service->memory_mb ? number_format($service->memory_mb/1024,1,',','.') . ' GB' : '—' }}</strong></div>
                <div><span>NÆSTE FAKTURA</span><strong>{{ $service->next_invoice_at ? \Carbon\Carbon::parse($service->next_invoice_at)->format('d.m.Y') : '—' }}</strong></div>
            </div>

            @if($service->last_error)
            <div class="notice" style="margin-top:12px;border-color:#69333d;color:#ffabb8">{{ \Pterodactyl\Services\Nodexa\CustomerBranding::serviceError() }}</div>
            @endif

            <div class="vpsActions">
                <a class="btn primary" href="{{ route('store.client.vps.show', $service->id) }}">Åbn kontrolpanel →</a>
                @if($service->status==='active' && $service->upstream_server_id)
                    <form method="POST" action="{{ route('store.client.vps.action',$service->id) }}">@csrf<input type="hidden" name="action" value="boot"><button class="btn">Start</button></form>
                    <form method="POST" action="{{ route('store.client.vps.action',$service->id) }}">@csrf<input type="hidden" name="action" value="restart"><button class="btn">Restart</button></form>
                    <form method="POST" action="{{ route('store.client.vps.action',$service->id) }}">@csrf<input type="hidden" name="action" value="shutdown"><button class="btn">Shutdown</button></form>
                    <form method="POST" action="{{ route('store.client.vps.action',$service->id) }}">@csrf<input type="hidden" name="action" value="refresh"><button class="btn">↻ Opdatér status</button></form>
                @elseif($service->status==='awaiting_payment')
                    <a class="btn primary" href="{{ route('store.vps.checkout',$service->id) }}">Åbn ordre →</a>
                @elseif($service->status==='provisioning_failed')
                    <span class="muted">Provisionering fejlede. Support/admin kan retry’e uden at oprette ny ordre.</span>
                @endif
            </div>

            @if($service->subscription_id)
                <a class="vpsCancelLink" href="{{ route('store.client.billing') }}#subscription-{{ $service->subscription_id }}">Administrér abonnement / opsig →</a>
            @endif

            <div class="vpsFoot">
                <span>{{ number_format((float)$service->monthly_price,2,',','.') }} {{ $service->currency }}/md</span>
                @if($service->expires_at)<span>Udløber {{ \Carbon\Carbon::parse($service->expires_at)->format('d.m.Y H:i') }}</span>@endif
                @if($service->last_synced_at)<span>Synk {{ \Carbon\Carbon::parse($service->last_synced_at)->diffForHumans() }}</span>@endif
            </div>
        </article>
        @empty
        <div class="card emptyState" style="grid-column:1/-1"><div class="icon">☁</div><h2>Ingen VPS-services endnu</h2><p class="muted">Bestil en VPS gennem Nodexa. Efter betaling bliver den automatisk oprettet på Nodexas VPS-platform.</p><a class="btn primary" href="{{ route('store.vps') }}">Se VPS-pakker →</a></div>
        @endforelse
    </div>
</div>
</section>

@push('styles')
<style>
.clientHead{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px}.clientHead h1{font-size:38px;margin:5px 0}.clientHead p{margin:0}.vpsCancelLink{display:inline-flex;margin-top:13px;font-size:11px;color:#96bce6;text-decoration:underline;text-underline-offset:3px}.vpsCancelLink:hover{color:#e7f2ff}.vpsGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.vpsCard{border:1px solid var(--line);border-radius:17px;background:#07131f;padding:20px}.vpsTop{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.vpsTop h2{font-size:20px;margin:5px 0 2px}.vpsNameLink{color:inherit;text-decoration:none}.vpsNameLink:hover,.vpsNameLink:focus-visible{color:#88baff;text-decoration:underline}.vpsTop p{margin:0;color:#7188a3;font-size:11px}.serviceState{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:900;white-space:nowrap}.serviceState.good{background:#0d3025;color:#88e7bf}.serviceState.warn{background:#30250d;color:#ffd077}.serviceState.bad{background:#34161d;color:#ff9eae}.vpsStats{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:18px}.vpsStats>div{border:1px solid #17304a;border-radius:11px;background:#081522;padding:12px}.vpsStats span{display:block;color:#66809c;font-size:8px;font-weight:850;letter-spacing:.08em}.vpsStats strong{display:block;margin-top:4px;font-size:12px;overflow-wrap:anywhere}.vpsActions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.vpsActions form{margin:0}.vpsActions .btn{min-height:36px;padding:0 12px;font-size:11px}.vpsFoot{display:flex;gap:14px;flex-wrap:wrap;margin-top:15px;padding-top:13px;border-top:1px solid var(--line);font-size:9px;color:#627b97}@media(max-width:850px){.vpsGrid{grid-template-columns:1fr}}@media(max-width:580px){.clientHead{align-items:flex-start;flex-direction:column}.vpsStats{grid-template-columns:1fr}.vpsTop{flex-direction:column}}
</style>
@endpush
@endsection