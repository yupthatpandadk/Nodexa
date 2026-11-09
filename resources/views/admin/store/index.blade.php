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

<div class="box box-info" id="egg-hosting-packages">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-cubes"></i> Pterodactyl Egg-pakker</h3>
        <span class="label label-info pull-right">Starter · Plus · Pro · Ultimate</span>
    </div>
    <form method="POST" action="{{ route('admin.store.egg-packages.generate') }}">
        @csrf
        <div class="box-body">
            <p style="font-size:12px;line-height:1.6">
                Opret fire serverpakker med tilpasset RAM, CPU, disk, backups,
                databaser og pris ud fra det Egg, du har installeret i Pterodactyl.
                <strong>Alle nye pakker oprettes som skjulte</strong>, indtil du godkender dem.
            </p>
            <div class="form-group">
                <label for="eggPresetEgg">Pterodactyl Egg</label>
                <select name="egg_id" id="eggPresetEgg" class="form-control" required>
                    <option value="">Vælg et installeret Egg...</option>
                    @foreach($eggs as $egg)
                        <option value="{{ $egg->id }}" data-suggested-category="{{ $eggPackageDetection[$egg->id] ?? 'generic' }}" {{ (string)old('egg_id') === (string)$egg->id ? 'selected' : '' }}>
                            {{ $egg->name }} · {{ $egg->nest?->name ?? 'Andre' }} (#{{ $egg->id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="eggPresetCategory">Kategori</label>
                <select name="category" id="eggPresetCategory" class="form-control" required>
                    <option value="auto" {{ old('category', 'auto') === 'auto' ? 'selected' : '' }}>Automatisk ud fra Egg</option>
                    @foreach($eggPackageCategories as $key => $label)
                        <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <p class="help-block">Forslagene tager højde for spiltypen. Tilpas månedlig pris inden oprettelse, og kontrollér serverens kapacitet og omkostninger før aktivering.</p>
            <div class="table-responsive">
                <table class="table table-condensed" style="margin-bottom:4px">
                    <thead><tr><th>Pakke</th><th>Ressourcer (forslag)</th><th>Pris (DKK/md.)</th></tr></thead>
                    <tbody>
                        @foreach($eggPackageTiers as $tier => $tierLabel)
                        <tr>
                            <td style="white-space:nowrap">
                                <label style="font-weight:600">
                                    <input type="checkbox" name="tiers[]" value="{{ $tier }}" {{ in_array($tier, old('tiers', array_keys($eggPackageTiers))) ? 'checked' : '' }}>
                                    {{ $tierLabel }}
                                </label>
                            </td>
                            <td><span id="eggPresetSpecs-{{ $tier }}" style="font-size:11px;color:#718096">Vælg et Egg</span></td>
                            <td style="min-width:110px">
                                <input type="number" name="prices[{{ $tier }}]" class="form-control input-sm" min="0.01" max="99999" step="0.01" value="{{ old('prices.'.$tier) }}" placeholder="Forslag" aria-label="Pris for {{ $tierLabel }}">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="help-block" id="eggPresetHint" role="status">Prisfelterne kan være tomme for at anvende den foreslåede pris.</p>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-info btn-block" {{ $eggs->isEmpty() ? 'disabled' : '' }}>
                <i class="fa fa-magic"></i> Opret valgte pakker som skjulte produkter
            </button>
            <p class="help-block" style="margin-bottom:0">Eksisterende pakker, ordrer og servere ændres ikke. Genimport springer allerede oprettede pakker over.</p>
        </div>
    </form>
</div>

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
@foreach($products as $p)<tr><td><b>{{$p->name}}</b><br><small>{{$p->game}}</small></td><td><small>{{$p->memory}}MB RAM · {{$p->cpu}}% CPU · {{$p->disk}}MB disk</small></td><td>{{$p->price_monthly}} DKK</td><td>{{$p->enabled?'Aktiv':'Skjult'}}{{$p->featured?' · ★':''}}</td><td><form method="POST" action="{{route('admin.store.products.update',$p)}}">@csrf @method('PATCH')<input type="hidden" name="enabled" value="{{$p->enabled?0:1}}"><input type="hidden" name="featured" value="{{$p->featured?1:0}}"><button class="btn btn-xs btn-default">{{$p->enabled?'Deaktivér':'Aktivér'}}</button></form>
<details class="eggProductEditor" style="margin-top:7px;min-width:190px">
    <summary style="font-size:11px;cursor:pointer;color:#2878ba">Redigér pakke og pris</summary>
    <form method="POST" action="{{ route('admin.store.products.details', $p) }}" style="padding:9px 0 0;display:grid;gap:7px;min-width:195px">
        @csrf @method('PATCH')
        <label style="font-size:11px">Navn<input name="name" class="form-control input-sm" value="{{ $p->name }}" required maxlength="100"></label>
        <label style="font-size:11px">Kategori<input name="game" class="form-control input-sm" value="{{ $p->game }}" required maxlength="80"></label>
        <label style="font-size:11px">Beskrivelse<textarea name="description" class="form-control input-sm" rows="2" maxlength="2000">{{ $p->description }}</textarea></label>
        <label style="font-size:11px">Pris DKK/md.<input name="price_monthly" type="number" step=".01" min="0" max="99999" class="form-control input-sm" value="{{ $p->price_monthly }}" required></label>
        <div class="row" style="margin:0">
            @foreach(['memory'=>'RAM MB','cpu'=>'CPU %','disk'=>'Disk MB','databases'=>'Databaser','backups'=>'Backups','allocations'=>'Allocations'] as $key => $label)
                <label class="col-xs-6" style="padding:2px 3px;font-size:11px">
                    {{ $label }}
                    <input class="form-control input-sm" type="number" name="{{ $key }}" min="{{ $key === 'memory' ? 128 : ($key === 'disk' ? 256 : ($key === 'cpu' ? 10 : ($key === 'allocations' ? 1 : 0))) }}" value="{{ $p->$key }}" required>
                </label>
            @endforeach
        </div>
        <button type="submit" class="btn btn-xs btn-primary">Gem ændringer</button>
        <small class="text-muted">Gælder kun nye bestillinger.</small>
    </form>
</details>
</td></tr>@endforeach
</table></div></div>

<div class="box"><div class="box-header"><h3 class="box-title">Ordrer & provisioning</h3></div><div class="box-body table-responsive"><table class="table"><tr><th>#</th><th>Kunde</th><th>Server</th><th>Beløb</th><th>Status</th><th>Handling</th></tr>
@foreach($orders as $o)<tr><td>#{{$o->id}}</td><td>{{$o->user->email??'—'}}<br><small>{{$o->product->name??'—'}}</small></td><td>{{$o->server_name??'—'}}@if($o->server)<br><a href="{{route('admin.servers.view',$o->server)}}">Server #{{$o->server->id}}</a>@endif</td><td>{{$o->amount}} {{$o->currency}}</td><td><span class="label {{$o->status==='active'?'label-success':($o->status==='awaiting_payment'?'label-warning':'label-default')}}">{{str_replace('_',' ',$o->status)}}</span></td><td>@if(!$o->hidden_from_client)
<form method="POST" action="{{route('admin.store.orders.status',$o)}}">@csrf @method('PATCH')<select name="status" class="form-control input-sm" onchange="this.form.submit()"><option value="">Skift status…</option>@foreach(['awaiting_payment'=>'Afventer betaling','paid'=>'Betalt + provisionér','active'=>'Aktiv','cancelled'=>'Annulleret','refunded'=>'Refunderet'] as $v=>$l)<option value="{{$v}}">{{$l}}</option>@endforeach</select></form>
@else <small class="text-muted">Arkiveret / kan ikke genaktiveres</small> @endif
@if(in_array($o->status,['awaiting_payment','failed'],true) && !$o->paid_at && !$o->server_id && !$o->payment_reference)
<form method="POST" action="{{route('admin.store.orders.remove',$o)}}" style="margin-top:5px" onsubmit="return confirm('Annuller og fjern denne ubetalte ordre fra kundens oversigt?');">
@csrf @method('DELETE')
<button class="btn btn-xs btn-danger" type="submit">Slet ubetalt ordre</button>
</form>
@endif
</td></tr>@endforeach
</table></div></div></div>
</div></div>
<script>
(function () {
    var egg = document.getElementById('eggPresetEgg');
    var category = document.getElementById('eggPresetCategory');
    if (!egg || !category) return;
    var profiles = @json($eggPackageProfiles);
    var tiers = @json(array_keys($eggPackageTiers));

    function refreshPreview() {
        var selected = egg.options[egg.selectedIndex];
        var game = category.value === 'auto'
            ? (selected && selected.dataset.suggestedCategory || 'generic')
            : category.value;
        var packages = profiles[game] || profiles.generic;
        tiers.forEach(function (tier) {
            var p = packages[tier];
            var info = document.getElementById('eggPresetSpecs-' + tier);
            var input = document.querySelector('[name="prices[' + tier + ']"]');
            if (!p) return;
            if (info) {
                info.textContent = (p.memory / 1024) + ' GB RAM · ' + p.cpu
                    + '% CPU · ' + (p.disk / 1024) + ' GB SSD · '
                    + p.backups + ' backups';
            }
            if (input) input.placeholder = p.price.toFixed(2) + ' kr';
        });
        var hint = document.getElementById('eggPresetHint');
        if (hint) hint.textContent = 'Prisfelter uden indtastning bruger de viste forslag. Priser er vejledende og kan ændres før offentliggørelse.';
    }

    egg.addEventListener('change', refreshPreview);
    category.addEventListener('change', refreshPreview);
    refreshPreview();
})();
</script>
@endsection
