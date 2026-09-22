@extends('layouts.admin')
@section('title','Storefront & Billing')
@section('content-header')<h1>Storefront & Billing <small>Produkter, ordrer, rabatter og provisioning</small></h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
<div class="row">
<div class="col-md-4">
<div class="box box-primary"><div class="box-header"><h3 class="box-title"><i class="fa fa-plus-circle"></i> Nyt serverprodukt</h3></div>
<form method="POST" action="{{route('admin.store.products.store')}}">@csrf<div class="box-body">
@foreach(['name'=>'Navn','game'=>'Spil / kategori','price_monthly'=>'Pris pr. måned (DKK)','memory'=>'RAM (MB)','disk'=>'Disk (MB)','cpu'=>'CPU %','databases'=>'Databaser','backups'=>'Backups','allocations'=>'Allocations'] as $n=>$l)<div class="form-group"><label>{{$l}}</label><input class="form-control" name="{{$n}}" required></div>@endforeach
<div class="form-group"><label>Beskrivelse</label><textarea class="form-control" name="description" rows="3"></textarea></div>
<div class="form-group"><label>Egg</label><select class="form-control" name="egg_id">@foreach($eggs as $egg)<option value="{{$egg->id}}">{{$egg->name}} (#{{$egg->id}})</option>@endforeach</select></div>
<label><input type="checkbox" name="enabled" value="1" checked> Aktiv</label>&nbsp;&nbsp;<label><input type="checkbox" name="featured" value="1"> Fremhævet</label>
</div><div class="box-footer"><button class="btn btn-primary btn-block">Opret produkt</button></div></form></div>

<div class="box"><div class="box-header"><h3 class="box-title"><i class="fa fa-ticket"></i> Rabatkoder</h3></div><form method="POST" action="{{route('admin.store.coupons.store')}}">@csrf<div class="box-body">
<div class="form-group"><label>Kode</label><input class="form-control" name="code" placeholder="WELCOME20" required></div>
<div class="row"><div class="col-xs-6"><label>Type</label><select class="form-control" name="type"><option value="percent">Procent</option><option value="fixed">Fast DKK</option></select></div><div class="col-xs-6"><label>Værdi</label><input class="form-control" type="number" step=".01" name="value" required></div></div><br>
<div class="form-group"><label>Maks. anvendelser</label><input class="form-control" type="number" name="max_uses"></div>
<div class="form-group"><label>Udløber</label><input class="form-control" type="datetime-local" name="expires_at"></div>
<button class="btn btn-default btn-block">Opret rabatkode</button></div></form>
<div class="box-body table-responsive"><table class="table table-condensed"><tr><th>Kode</th><th>Rabat</th><th>Brugt</th></tr>@foreach($coupons as $c)<tr><td><b>{{$c->code}}</b></td><td>{{$c->value}}{{$c->type==='percent'?'%':' DKK'}}</td><td>{{$c->uses}}{{$c->max_uses?'/'.$c->max_uses:''}}</td></tr>@endforeach</table></div></div>
</div>

<div class="col-md-8">
<div class="row"><div class="col-sm-4"><div class="small-box bg-aqua"><div class="inner"><h3>{{$products->count()}}</h3><p>Produkter</p></div><div class="icon"><i class="fa fa-cubes"></i></div></div></div><div class="col-sm-4"><div class="small-box bg-yellow"><div class="inner"><h3>{{$orders->where('status','awaiting_payment')->count()}}</h3><p>Afventer betaling</p></div><div class="icon"><i class="fa fa-clock-o"></i></div></div></div><div class="col-sm-4"><div class="small-box bg-green"><div class="inner"><h3>{{$orders->where('status','active')->count()}}</h3><p>Aktive ordrer</p></div><div class="icon"><i class="fa fa-check"></i></div></div></div></div>

<div class="box"><div class="box-header"><h3 class="box-title">Produkter</h3><a href="{{route('store.index')}}" target="_blank" class="btn btn-xs btn-primary pull-right">Åbn storefront</a></div><div class="box-body table-responsive"><table class="table"><tr><th>Produkt</th><th>Ressourcer</th><th>Pris</th><th>Status</th><th></th></tr>
@foreach($products as $p)<tr><td><b>{{$p->name}}</b><br><small>{{$p->game}}</small></td><td><small>{{$p->memory}}MB RAM · {{$p->cpu}}% CPU · {{$p->disk}}MB disk</small></td><td>{{$p->price_monthly}} DKK</td><td>{{$p->enabled?'Aktiv':'Skjult'}}{{$p->featured?' · ★':''}}</td><td><form method="POST" action="{{route('admin.store.products.update',$p)}}">@csrf @method('PATCH')<input type="hidden" name="enabled" value="{{$p->enabled?0:1}}"><input type="hidden" name="featured" value="{{$p->featured?1:0}}"><button class="btn btn-xs btn-default">{{$p->enabled?'Deaktivér':'Aktivér'}}</button></form></td></tr>@endforeach
</table></div></div>

<div class="box"><div class="box-header"><h3 class="box-title">Ordrer & provisioning</h3></div><div class="box-body table-responsive"><table class="table"><tr><th>#</th><th>Kunde</th><th>Server</th><th>Beløb</th><th>Status</th><th>Handling</th></tr>
@foreach($orders as $o)<tr><td>#{{$o->id}}</td><td>{{$o->user->email??'—'}}<br><small>{{$o->product->name??'—'}}</small></td><td>{{$o->server_name??'—'}}@if($o->server)<br><a href="{{route('admin.servers.view',$o->server)}}">Server #{{$o->server->id}}</a>@endif</td><td>{{$o->amount}} {{$o->currency}}</td><td><span class="label {{$o->status==='active'?'label-success':($o->status==='awaiting_payment'?'label-warning':'label-default')}}">{{str_replace('_',' ',$o->status)}}</span></td><td><form method="POST" action="{{route('admin.store.orders.status',$o)}}">@csrf @method('PATCH')<select name="status" class="form-control input-sm" onchange="this.form.submit()"><option value="">Skift status…</option>@foreach(['awaiting_payment'=>'Afventer betaling','paid'=>'Betalt + provisionér','active'=>'Aktiv','cancelled'=>'Annulleret','refunded'=>'Refunderet'] as $v=>$l)<option value="{{$v}}">{{$l}}</option>@endforeach</select></form></td></tr>@endforeach
</table></div></div></div>
</div></div>
@endsection
