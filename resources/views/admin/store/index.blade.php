@extends('layouts.admin')
@section('title','Storefront & Billing')
@section('content-header')<h1>Storefront & Billing <small>Produkter, ordrer, rabatter og provisioning</small></h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{$errors->first()}}</div>@endif
<div class="row">
<div class="col-md-4">
<div class="box box-primary"><div class="box-header"><h3 class="box-title"><i class="fa fa-plus-circle"></i> Nyt serverprodukt</h3></div>
<form method="POST" action="{{route('admin.store.products.store')}}">@csrf<div class="box-body">
@foreach(['name'=>'Navn','game'=>'Spil / kategori','price_monthly'=>'Pris pr. måned (DKK)','memory'=>'RAM (MB)','disk'=>'Disk (MB)','cpu'=>'CPU %','databases'=>'Databaser','backups'=>'Backups','allocations'=>'Allocations'] as $n=>$l)<div class="form-group"><label>{{$l}}</label><input class="form-control" name="{{$n}}" required></div>@endforeach
<div class="form-group"><label>Beskrivelse</label><textarea class="form-control" name="description" rows="3"></textarea></div>
<div class="form-group"><label>Egg</label><select class="form-control" name="egg_id">@foreach($eggs as $egg)<option value="{{$egg->id}}">{{$egg->name}} (#{{$egg->id}})</option>@endforeach</select></div>
<label><input type="checkbox" name="enabled" value="1" checked> Aktiv</label>&nbsp;&nbsp;<label><input type="checkbox" name="featured" value="1"> Fremhævet</label>
</div><div class="box-footer"><button class="btn btn-primary btn-block">Opret produkt</button></div></form></div>

<div class="box box-success">
<div class="box-header">
    <h3 class="box-title"><i class="fa fa-percent"></i> Globalt udsalg</h3>
    @if($globalSale->enabled && $globalSale->percent > 0)
        <span class="label label-success pull-right">{{number_format($globalSale->percent,0,',','.')}}% aktiv</span>
    @else
        <span class="label label-default pull-right">Deaktiveret</span>
    @endif
</div>
<form method="POST" action="{{route('admin.store.sale')}}">
@csrf
<div class="box-body">
    <div class="form-group">
        <label>Navn / label</label>
        <input class="form-control" name="label" maxlength="120" value="{{$globalSale->label}}" placeholder="Black Friday">
    </div>
    <div class="row">
        <div class="col-xs-6">
            <div class="form-group">
                <label>Rabat på alle varer</label>
                <div class="input-group">
                    <input class="form-control" type="number" name="percent" min="0" max="100" step=".01" value="{{$globalSale->percent}}" required>
                    <span class="input-group-addon">%</span>
                </div>
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                <label>Rabatperiode</label>
                <div class="input-group">
                    <input class="form-control" type="number" name="duration_months" min="1" max="120" value="{{$globalSale->duration_months}}" required>
                    <span class="input-group-addon">mdr.</span>
                </div>
            </div>
        </div>
    </div>
    <label><input type="checkbox" name="enabled" value="1" {{$globalSale->enabled?'checked':''}}> Aktivér globalt udsalg</label>
    <p class="help-block">
        Gælder nye game-server- og VPS-køb. Eksisterende abonnementer ændres ikke.
        Rabatkoder beregnes bagefter på den allerede nedsatte udsalgspris.
    </p>
</div>
<div class="box-footer">
    <button class="btn btn-success btn-block"><i class="fa fa-save"></i> Gem globalt udsalg</button>
</div>
</form>
</div>

<div class="box"><div class="box-header"><h3 class="box-title"><i class="fa fa-ticket"></i> Rabatkoder</h3></div><form method="POST" action="{{route('admin.store.coupons.store')}}">@csrf<div class="box-body">
<div class="form-group"><label>Kode</label><input class="form-control" name="code" placeholder="WELCOME20" required></div>
<div class="row"><div class="col-xs-6"><label>Type</label><select class="form-control" name="type"><option value="percent">Procent</option><option value="fixed">Fast DKK</option></select></div><div class="col-xs-6"><label>Værdi</label><input class="form-control" type="number" step=".01" name="value" required></div></div><br>
<div class="form-group"><label>Rabatperiode</label><div class="input-group"><input class="form-control" type="number" name="duration_months" min="1" max="120" value="1" required><span class="input-group-addon">måned(er)</span></div><p class="help-block">Måned 1 er den første betaling. Fx 3 = rabat på måned 1, 2 og 3.</p></div>
<div class="form-group"><label>Maks. anvendelser</label><input class="form-control" type="number" name="max_uses"></div>
<div class="form-group"><label>Udløber</label><input class="form-control" type="datetime-local" name="expires_at"></div>
<button class="btn btn-default btn-block">Opret rabatkode</button></div></form>
<div class="box-body table-responsive"><table class="table table-condensed"><tr><th>Kode</th><th>Rabat</th><th>Periode</th><th>Brugt</th></tr>@foreach($coupons as $c)<tr><td><b>{{$c->code}}</b></td><td>{{$c->value}}{{$c->type==='percent'?'%':' DKK'}}</td><td>{{$c->duration_months ?? 1}} mdr.</td><td>{{$c->uses}}{{$c->max_uses?'/'.$c->max_uses:''}}</td></tr>@endforeach</table></div></div>

<div class="box box-info">
<div class="box-header"><h3 class="box-title"><i class="fa fa-credit-card"></i> Stripe betaling</h3>
@if($stripeSettings->enabled)<span class="label label-success pull-right">Aktiv</span>@else<span class="label label-default pull-right">Deaktiveret</span>@endif
</div>
<form method="POST" action="{{route('admin.store.stripe.settings')}}">@csrf
<div class="box-body">
<div class="form-group">
<label>Stripe Secret Key</label>
<input class="form-control" type="password" name="secret_key" autocomplete="new-password" placeholder="{{$stripeSettings->has_secret_key?'Gemt — lad feltet være tomt for at beholde':'sk_live_... eller sk_test_...'}}">
<p class="help-block">Gemmes krypteret. Nøglen vises ikke igen efter lagring.</p>
</div>
<div class="form-group">
<label>Webhook Secret</label>
<input class="form-control" type="password" name="webhook_secret" autocomplete="new-password" placeholder="{{$stripeSettings->has_webhook_secret?'Gemt — lad feltet være tomt for at beholde':'whsec_...'}}">
<p class="help-block">Webhook endpoint: <code>{{route('stripe.webhook')}}</code><br>
Lyt til: <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code> og <code>checkout.session.expired</code>.</p>
</div>
<label><input type="checkbox" name="enabled" value="1" {{$stripeSettings->enabled?'checked':''}}> Aktivér Stripe Checkout</label>
@if($stripeSettings->last_tested_at)
<hr>
<small class="text-muted">Senest testet: {{$stripeSettings->last_tested_at}} · {{$stripeSettings->last_test_status}}@if($stripeSettings->last_test_message) · {{$stripeSettings->last_test_message}}@endif</small>
@endif
</div>
<div class="box-footer">
<button class="btn btn-info btn-block"><i class="fa fa-save"></i> Gem Stripe</button>
</div>
</form>
<form method="POST" action="{{route('admin.store.stripe.test')}}" style="padding:0 10px 10px">@csrf
<button class="btn btn-default btn-block"><i class="fa fa-plug"></i> Test Stripe forbindelse</button>
</form>
</div>
</div>

<div class="col-md-8">
<div class="row"><div class="col-sm-4"><div class="small-box bg-aqua"><div class="inner"><h3>{{$products->count()}}</h3><p>Produkter</p></div><div class="icon"><i class="fa fa-cubes"></i></div></div></div><div class="col-sm-4"><div class="small-box bg-yellow"><div class="inner"><h3>{{$orders->where('status','awaiting_payment')->count()}}</h3><p>Afventer betaling</p></div><div class="icon"><i class="fa fa-clock-o"></i></div></div></div><div class="col-sm-4"><div class="small-box bg-green"><div class="inner"><h3>{{$orders->where('status','active')->count()}}</h3><p>Aktive ordrer</p></div><div class="icon"><i class="fa fa-check"></i></div></div></div></div>

<div class="box"><div class="box-header"><h3 class="box-title">Produkter</h3><a href="{{route('store.index')}}" target="_blank" class="btn btn-xs btn-primary pull-right">Åbn storefront</a></div><div class="box-body table-responsive"><table class="table"><tr><th>Produkt</th><th>Ressourcer</th><th>Pris</th><th>Status</th><th></th></tr>
@foreach($products as $p)<tr><td><b>{{$p->name}}</b><br><small>{{$p->game}}</small></td><td><small>{{$p->memory}}MB RAM · {{$p->cpu}}% CPU · {{$p->disk}}MB disk</small></td><td>{{$p->price_monthly}} DKK</td><td>{{$p->enabled?'Aktiv':'Skjult'}}{{$p->featured?' · ★':''}}</td><td><form method="POST" action="{{route('admin.store.products.update',$p)}}">@csrf @method('PATCH')<input type="hidden" name="enabled" value="{{$p->enabled?0:1}}"><input type="hidden" name="featured" value="{{$p->featured?1:0}}"><button class="btn btn-xs btn-default">{{$p->enabled?'Deaktivér':'Aktivér'}}</button></form></td></tr>@endforeach
</table></div></div>

<div class="box"><div class="box-header"><h3 class="box-title">Ordrer & provisioning</h3></div><div class="box-body table-responsive"><table class="table"><tr><th>#</th><th>Kunde</th><th>Server</th><th>Beløb</th><th>Status</th><th>Handling</th></tr>
@foreach($orders as $o)<tr><td>#{{$o->id}}</td><td>{{$o->user->email??'—'}}<br><small>{{$o->product->name??'—'}}</small></td><td>{{$o->server_name??'—'}}@if($o->server)<br><a href="{{route('admin.servers.view',$o->server)}}">Server #{{$o->server->id}}</a>@endif</td><td>{{$o->amount}} {{$o->currency}}</td><td><span class="label {{$o->status==='active'?'label-success':($o->status==='awaiting_payment'?'label-warning':'label-default')}}">{{str_replace('_',' ',$o->status)}}</span></td><td><form method="POST" action="{{route('admin.store.orders.status',$o)}}">@csrf @method('PATCH')<select name="status" class="form-control input-sm" onchange="this.form.submit()"><option value="">Skift status…</option>@foreach(['awaiting_payment'=>'Afventer betaling','paid'=>'Betalt + provisionér','active'=>'Aktiv','cancelled'=>'Annulleret','refunded'=>'Refunderet'] as $v=>$l)<option value="{{$v}}">{{$l}}</option>@endforeach</select></form>
@if(in_array($o->status,['awaiting_payment','failed'],true) && !$o->paid_at && !$o->server_id && !$o->payment_reference)
<form method="POST" action="{{route('admin.store.orders.remove',$o)}}" style="margin-top:5px" onsubmit="return confirm('Annuller og fjern denne ubetalte ordre fra kundens oversigt?');">
@csrf @method('DELETE')
<button class="btn btn-xs btn-danger" type="submit">Slet ubetalt ordre</button>
</form>
@endif
</td></tr>@endforeach
</table></div></div></div>
</div></div>
@endsection
