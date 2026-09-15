@extends('layouts.admin')
@php
$vd=[];$vf='/var/lib/nodexa/version.json';if(is_readable($vf)){ $d=json_decode((string)file_get_contents($vf),true);$vd=is_array($d)?$d:[];}$nv=(string)($vd['version']??'unknown');$nc=(string)($vd['commit']??'');
@endphp
@section('title','Dashboard')
@section('content-header')
<h1>Dashboard <small>Nodexa Business & Infrastructure Overview</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Dashboard</li></ol>
@endsection
@section('content')
<style>
.nx-hero{background:linear-gradient(135deg,rgba(28,39,62,.98),rgba(20,28,45,.98));border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:22px;margin-bottom:20px}.nx-hero h2{margin:0 0 5px;font-weight:750}.nx-muted{opacity:.68}.nx-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.nx-stat{background:var(--nodexa-admin-card,#1d2638);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:17px;min-height:108px}.nx-stat .ico{font-size:18px;opacity:.7}.nx-stat .num{font-size:27px;font-weight:800;margin-top:9px;line-height:1}.nx-stat .lbl{font-size:12px;opacity:.65;margin-top:7px}.nx-panel{border-radius:12px!important;overflow:hidden}.nx-actions{display:flex;gap:8px;flex-wrap:wrap}.nx-order{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid rgba(255,255,255,.07)}.nx-order:last-child{border:0}.nx-badge{padding:4px 8px;border-radius:99px;background:rgba(255,255,255,.08);font-size:11px;text-transform:uppercase}.nx-alert{border-radius:9px;padding:11px 13px;margin-bottom:8px;background:rgba(255,255,255,.05)}@media(max-width:1000px){.nx-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.nx-grid{grid-template-columns:1fr}.nx-hero{padding:17px}}
</style>
<div class="nx-hero"><div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap"><div><div class="nx-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.1em">Nodexa Control Center</div><h2>Velkommen tilbage</h2><div class="nx-muted">Virksomhed, kunder, økonomi og infrastruktur samlet ét sted.</div></div><div style="text-align:right"><strong>v{{ $nv }}</strong>@if($nc)<div class="nx-muted"><code>{{ substr($nc,0,10) }}</code></div>@endif</div></div></div>
<div class="nx-grid">
<div class="nx-stat"><i class="fa fa-users ico"></i><div class="num">{{ number_format($metrics['customers'],0,',','.') }}</div><div class="lbl">Kunder</div></div>
<div class="nx-stat"><i class="fa fa-server ico"></i><div class="num">{{ $metrics['active_servers'] }} / {{ $metrics['servers'] }}</div><div class="lbl">Aktive servere / total</div></div>
<div class="nx-stat"><i class="fa fa-line-chart ico"></i><div class="num">{{ number_format($metrics['mrr'],2,',','.') }}</div><div class="lbl">Estimeret MRR</div></div>
<div class="nx-stat"><i class="fa fa-money ico"></i><div class="num">{{ number_format($metrics['revenue_month'],2,',','.') }}</div><div class="lbl">Betalt denne måned</div></div>
<div class="nx-stat"><i class="fa fa-shopping-cart ico"></i><div class="num">{{ $metrics['pending_orders'] }}</div><div class="lbl">Ordrer i kø</div></div>
<div class="nx-stat"><i class="fa fa-ticket ico"></i><div class="num">{{ $metrics['open_tickets'] }}</div><div class="lbl">Åbne tickets</div></div>
<div class="nx-stat"><i class="fa fa-file-text-o ico"></i><div class="num">{{ number_format($metrics['unpaid'],2,',','.') }}</div><div class="lbl">Ubetalt saldo</div></div>
<div class="nx-stat"><i class="fa fa-sitemap ico"></i><div class="num">{{ $metrics['nodes'] }}</div><div class="lbl">Nodes</div></div>
</div>
<div class="row"><div class="col-md-7"><div class="box box-primary nx-panel"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-clock-o"></i> Seneste ordrer</h3></div><div class="box-body">@forelse($recentOrders as $o)<div class="nx-order"><div><strong>#{{ $o->id }} · {{ $o->product_name ?: 'Produkt' }}</strong><div class="nx-muted">{{ $o->username ?: 'Ukendt kunde' }} · {{ $o->created_at }}</div></div><span class="nx-badge">{{ $o->status }}</span></div>@empty<div class="nx-muted">Ingen ordrer endnu.</div>@endforelse</div></div></div>
<div class="col-md-5"><div class="box box-primary nx-panel"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bell"></i> Kræver opmærksomhed</h3></div><div class="box-body">@forelse($alerts as $a)<div class="nx-alert"><i class="fa {{ $a['icon'] }}"></i>&nbsp; {{ $a['text'] }}</div>@empty<div class="nx-muted"><i class="fa fa-check-circle"></i> Alt ser roligt ud lige nu.</div>@endforelse</div></div></div></div>
<div class="box box-primary nx-panel"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bolt"></i> Hurtige handlinger</h3></div><div class="box-body nx-actions"><a class="btn btn-primary" href="{{ route('admin.servers.new') }}"><i class="fa fa-plus"></i> Ny server</a><a class="btn btn-default" href="{{ route('admin.servers') }}"><i class="fa fa-server"></i> Servere</a><a class="btn btn-default" href="{{ route('admin.nodes') }}"><i class="fa fa-sitemap"></i> Nodes</a><a class="btn btn-default" href="{{ route('admin.updates') }}"><i class="fa fa-cloud-download"></i> Update Center</a></div></div>
@endsection
