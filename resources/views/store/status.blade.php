@extends('store.layout')
@section('title','Systemstatus')
@section('description','Aktuel status for Nodexa tjenester, nodes og incidents.')

@push('styles')
<style>
.statusHero{padding:76px 0 42px;text-align:center;background:radial-gradient(700px 300px at 50% 0,rgba(61,141,255,.2),transparent 72%);border-bottom:1px solid #11263b}.statusHero h1{font-size:clamp(38px,6vw,62px);margin:8px 0 10px}.statusHero p{color:#8fa3bd;margin:0 auto;max-width:720px}.overall{display:inline-flex;align-items:center;gap:9px;margin-top:20px;padding:9px 14px;border-radius:999px;border:1px solid #21415e;background:#091827;color:#cfe6f8;font-size:12px;font-weight:800}.overall .dot{width:8px;height:8px;border-radius:50%;background:{{ $allOperational ? '#47d99c' : '#ffb454' }};box-shadow:0 0 14px {{ $allOperational ? '#47d99c' : '#ffb454' }}}.statusShell{padding:46px 0 80px}.statusGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.statusCard{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 19px;border:1px solid #18324c;border-radius:14px;background:#081522}.statusCard h3{font-size:14px;margin:0}.statusCard p{font-size:11px;color:#7189a4;margin:4px 0 0}.statusBadge{font-size:10px;font-weight:900;padding:6px 9px;border-radius:999px;white-space:nowrap}.s-operational,.s-online{background:#0d3025;color:#86e7bf;border:1px solid #245b4a}.s-degraded{background:#30270f;color:#ffd986;border:1px solid #6d5726}.s-partial_outage,.s-maintenance{background:#2b1f0f;color:#ffc377;border:1px solid #70491d}.s-major_outage,.s-offline{background:#32161b;color:#ff9aa8;border:1px solid #74333e}.incident{padding:20px;border:1px solid #1b3853;border-radius:15px;background:#081522;margin-top:12px}.incidentTop{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}.incident h3{font-size:18px;margin:0 0 5px}.incident p{color:#9ab0c7;margin:0;white-space:pre-line}.timeline{margin-top:18px;padding-left:17px;border-left:2px solid #17334c}.timelineItem{padding:0 0 18px 15px;position:relative}.timelineItem:before{content:"";position:absolute;width:8px;height:8px;border-radius:50%;background:#579be0;left:-21px;top:5px}.timelineItem strong{font-size:12px}.timelineItem small{display:block;color:#607d99;margin-top:3px}.sectionTitle{font-size:28px;margin:40px 0 14px}.emptyStatus{padding:28px;border:1px dashed #2b4866;border-radius:14px;text-align:center;color:#8198b3;background:#07131f}@media(max-width:720px){.statusGrid{grid-template-columns:1fr}.incidentTop{flex-direction:column}}
</style>
@endpush

@section('content')
<section class="statusHero">
    <div class="container">
        <div class="eyebrow">NODEXA STATUS</div>
        <h1>Systemstatus</h1>
        <p>Live overblik over Nodexa-platformen, infrastruktur og aktive incidents.</p>
        <div class="overall"><span class="dot"></span>{{ $allOperational ? 'Alle systemer fungerer normalt' : 'Der er aktive driftsforstyrrelser' }}</div>
    </div>
</section>

<section class="statusShell">
    <div class="container">
        <h2 class="sectionTitle" style="margin-top:0">Tjenester</h2>
        @if($components->isNotEmpty())
            <div class="statusGrid">
                @foreach($components as $component)
                <div class="statusCard">
                    <div>
                        <h3>{{ $component->name }}</h3>
                        @if($component->description)<p>{{ $component->description }}</p>@endif
                    </div>
                    <span class="statusBadge s-{{ $component->status }}">{{ str_replace('_',' ',ucfirst($component->status)) }}</span>
                </div>
                @endforeach
            </div>
        @else
            <div class="emptyStatus">Der er endnu ikke oprettet offentlige status-komponenter.</div>
        @endif

        @if($health->isNotEmpty())
        <h2 class="sectionTitle">Nodes</h2>
        <div class="statusGrid">
            @foreach($health as $check)
            <div class="statusCard">
                <div><h3>{{ $check->node_name }}</h3><p>Senest kontrolleret {{ CarbonCarbon::parse($check->checked_at)->diffForHumans() }}@if($check->latency_ms) · {{ $check->latency_ms }} ms@endif</p></div>
                <span class="statusBadge s-{{ $check->status }}">{{ $check->status === 'online' ? 'Online' : 'Offline' }}</span>
            </div>
            @endforeach
        </div>
        @endif

        <h2 class="sectionTitle">Aktive incidents</h2>
        @forelse($activeIncidents as $incident)
            <article class="incident">
                <div class="incidentTop">
                    <div><h3>{{ $incident->title }}</h3><p>{{ $incident->message }}</p></div>
                    <span class="statusBadge s-{{ $incident->severity === 'critical' ? 'major_outage' : ($incident->severity === 'major' ? 'partial_outage' : 'degraded') }}">{{ ucfirst($incident->status) }}</span>
                </div>
                @if(($updates[$incident->id] ?? collect())->isNotEmpty())
                <div class="timeline">
                    @foreach($updates[$incident->id] as $update)
                    <div class="timelineItem"><strong>{{ ucfirst($update->status) }}</strong><p>{{ $update->message }}</p><small>{{ CarbonCarbon::parse($update->created_at)->format('d.m.Y H:i') }}</small></div>
                    @endforeach
                </div>
                @endif
            </article>
        @empty
            <div class="emptyStatus">Ingen aktive incidents.</div>
        @endforelse

        @if($recentIncidents->isNotEmpty())
        <h2 class="sectionTitle">Tidligere incidents</h2>
        @foreach($recentIncidents as $incident)
            <article class="incident">
                <div class="incidentTop">
                    <div><h3>{{ $incident->title }}</h3><p>{{ $incident->message }}</p></div>
                    <span class="statusBadge s-operational">Løst</span>
                </div>
                @if(($updates[$incident->id] ?? collect())->isNotEmpty())
                <div class="timeline">
                    @foreach(($updates[$incident->id] ?? collect())->take(5) as $update)
                    <div class="timelineItem"><strong>{{ ucfirst($update->status) }}</strong><p>{{ $update->message }}</p><small>{{ CarbonCarbon::parse($update->created_at)->format('d.m.Y H:i') }}</small></div>
                    @endforeach
                </div>
                @endif
            </article>
        @endforeach
        @endif
    </div>
</section>
@endsection