@extends('layouts.admin')
@section('title','VPS Hosting')
@section('content-header')<h1>VPS Hosting <small>Flax reseller integration</small></h1>@endsection
@section('content')
<style>
.vps-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:18px}.vps-card{background:#10263a;border:1px solid rgba(255,255,255,.07);border-radius:9px;overflow:hidden;margin-bottom:18px}.vps-card-head{padding:14px 17px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between;gap:12px}.vps-card-head h3{font-size:15px;font-weight:600;margin:0}.vps-card-body{padding:17px}.vps-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}.vps-stat{background:#10263a;border:1px solid rgba(255,255,255,.07);border-radius:9px;padding:15px}.vps-stat strong{display:block;font-size:24px;line-height:1.1;margin-bottom:5px}.vps-stat span{font-size:12px;color:#8da2b5}.vps-actions{display:flex;gap:9px;flex-wrap:wrap}.vps-os-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.vps-os{display:flex;align-items:center;gap:10px;padding:9px 11px;background:rgba(0,0,0,.12);border:1px solid rgba(255,255,255,.055);border-radius:6px}.vps-os-id{min-width:38px;text-align:center;padding:3px 6px;border-radius:4px;background:#0b1c2b;color:#6da9ff;font-family:monospace;font-size:12px}.vps-os-name{font-size:13px}.vps-table{margin:0}.vps-table>thead>tr>th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#8da2b5;border-bottom:1px solid rgba(255,255,255,.08)!important}.vps-table>tbody>tr>td{vertical-align:middle;border-top:1px solid rgba(255,255,255,.045)!important}.vps-muted{color:#8da2b5;font-size:12px}.vps-api .form-group{margin-bottom:12px}@media(max-width:900px){.vps-grid{grid-template-columns:1fr}.vps-stats{grid-template-columns:1fr}.vps-os-grid{grid-template-columns:1fr}}@media(min-width:901px){.vps-sticky{position:sticky;top:15px}}
</style>
@if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{$errors->first()}}</div>@endif
<div class="vps-stats">
 <div class="vps-stat"><strong>{{count($remotePackages)}}</strong><span>Flax VPS-pakker</span></div>
 <div class="vps-stat"><strong>{{count($operatingSystems)}}</strong><span>Operativsystemer</span></div>
 <div class="vps-stat"><strong>{{$services->count()}}</strong><span>Provisionerede VPS'er</span></div>
</div>
<div class="vps-grid">
 <div>
  <div class="vps-card">
   <div class="vps-card-head"><h3><i class="fa fa-cubes"></i> VPS-pakker hos Flax</h3><span class="vps-muted">{{count($remotePackages)}} fundet</span></div>
   @if(count($remotePackages))<div class="table-responsive"><table class="table vps-table"><thead><tr><th>ID</th><th>Pakke</th><th>Pris</th><th>RAM</th><th>Disk</th><th>vCPU</th></tr></thead><tbody>@foreach($remotePackages as $p)<tr><td><code>{{$p['id']??$p['product_id']??$p['productId']??'—'}}</code></td><td><strong>{{$p['name']??$p['title']??$p['product_name']??'VPS'}}</strong></td><td>{{$p['price']??$p['monthly_price']??$p['cost']??'—'}} {{$p['currency']??''}}</td><td>{{$p['memory_mb']??$p['ram_mb']??$p['memory']??'—'}}</td><td>{{$p['disk_mb']??$p['storage_mb']??$p['disk']??'—'}}</td><td>{{$p['vcores']??$p['cores']??$p['cpu']??'—'}}</td></tr>@endforeach</tbody></table></div>@else<div class="vps-card-body vps-muted">Ingen VPS-pakker fundet.</div>@endif
  </div>
  <div class="vps-card">
   <div class="vps-card-head"><h3><i class="fa fa-linux"></i> Operativsystemer</h3><span class="vps-muted">{{count($operatingSystems)}} fundet</span></div>
   <div class="vps-card-body">@if(count($operatingSystems))<div class="vps-os-grid">@foreach($operatingSystems as $os) @php($osId=$os['id']??$os['value']??$os['template_id']??$os['os_id']??'—') @php($osName=$os['name']??$os['label']??$os['title']??$os['os_name']??$os['filename']??'Ukendt OS')<div class="vps-os"><span class="vps-os-id">{{$osId}}</span><span class="vps-os-name">{{$osName}}</span></div>@endforeach</div>@else<span class="vps-muted">Ingen operativsystemer fundet.</span>@endif</div>
  </div>
  <div class="vps-card">
   <div class="vps-card-head"><h3><i class="fa fa-refresh"></i> Synkroniserede pakker</h3><span class="vps-muted">{{$packages->count()}} pakker</span></div>
   <div class="table-responsive"><table class="table vps-table"><thead><tr><th>Pakke</th><th>Flax ID</th><th>OS ID</th><th>vCPU</th><th>RAM</th><th>Disk</th><th>Pris</th></tr></thead><tbody>@forelse($packages as $p)<tr><td><strong>{{$p->name}}</strong></td><td>{{$p->provider_product_id?:'—'}}</td><td>{{$p->default_os_id?:'—'}}</td><td>{{$p->vcores?:'—'}}</td><td>{{number_format((int)$p->memory_mb)}} MB</td><td>{{number_format((int)$p->disk_mb)}} MB</td><td>{{number_format((float)$p->price,2,',','.')}} {{$p->currency}}</td></tr>@empty<tr><td colspan="7" class="vps-muted">Ingen VPS-pakker synkroniseret endnu.</td></tr>@endforelse</tbody></table></div>
  </div>
  <div class="vps-card">
   <div class="vps-card-head"><h3><i class="fa fa-server"></i> Provisionerede VPS'er</h3></div>
   <div class="table-responsive"><table class="table vps-table"><thead><tr><th>ID</th><th>Kunde</th><th>Hostname</th><th>Flax server</th><th>IP</th><th>Status</th></tr></thead><tbody>@forelse($services as $s)<tr><td>#{{$s->id}}</td><td>#{{$s->user_id}}</td><td>{{$s->hostname?:'—'}}</td><td>{{$s->provider_server_id?:'—'}}</td><td>{{$s->ip_address?:'—'}}</td><td>{{$s->status}}</td></tr>@empty<tr><td colspan="6" class="vps-muted">Ingen VPS-services endnu.</td></tr>@endforelse</tbody></table></div>
  </div>
 </div>
 <div>
  <div class="vps-sticky">
   <div class="vps-card vps-api">
    <div class="vps-card-head"><h3><i class="fa fa-cloud"></i> Flax API</h3>@if($provider)<span class="label label-success">Konfigureret</span>@endif</div>
    <form method="POST" action="{{route('admin.vps.provider')}}">@csrf @method('PATCH')<div class="vps-card-body"><div class="form-group"><label>API endpoint</label><input class="form-control" name="endpoint" required value="{{$provider->endpoint??'https://api.flaxhosting.dk'}}"></div><div class="form-group"><label>API-nøgle</label><input class="form-control" type="password" name="api_key" placeholder="{{$provider?'Lad stå tom for at beholde nuværende nøgle':'Indsæt Flax API-nøgle'}}"></div><p class="vps-muted">Nodexa bruger forbindelsen til pakker, OS og VPS-styring.</p><button class="btn btn-primary btn-block"><i class="fa fa-save"></i> Gem Flax API</button></div></form>
   </div>
   @if($provider)<div class="vps-card"><div class="vps-card-head"><h3><i class="fa fa-wrench"></i> Handlinger</h3></div><div class="vps-card-body"><div class="vps-actions"><form method="POST" action="{{route('admin.vps.test')}}">@csrf<button class="btn btn-success"><i class="fa fa-plug"></i> Test API</button></form><form method="POST" action="{{route('admin.vps.sync')}}">@csrf<button class="btn btn-primary"><i class="fa fa-refresh"></i> Synkroniser pakker</button></form></div></div></div>@endif
   @if($discoveryError)<div class="alert alert-warning"><strong>Automatisk opslag</strong><br>{{$discoveryError}}</div>@endif
  </div>
 </div>
</div>
@endsection