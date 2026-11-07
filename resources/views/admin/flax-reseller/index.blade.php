@extends('layouts.admin')
@section('title','Flax Reseller')
@section('content-header')
<h1>Flax Reseller <small>VPS-produkter, upstream provisioning og kundeservices.</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Flax Reseller</li></ol>
@endsection

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Kunne ikke gemme:</strong> {{ $errors->first() }}</div>@endif

<div class="row">
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cloud"></i> Flax API</h3>
                @if($settings?->last_test_status)
                    <span class="label {{ $settings->last_test_status === 'success' ? 'label-success' : 'label-danger' }} pull-right">
                        {{ $settings->last_test_status === 'success' ? 'Forbundet' : 'Fejl' }}
                    </span>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.flax.settings') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>API endpoint</label>
                        <input class="form-control" type="url" name="api_url" value="{{ old('api_url',$settings->api_url ?? 'https://api.flaxhosting.dk/') }}" placeholder="https://api.flaxhosting.dk/" required>
                        <p class="help-block">Flax Reseller API endpoint: <code>https://api.flaxhosting.dk/</code>. API-key og action sendes som POST-parametre.</p>
                    </div>
                    <div class="form-group">
                        <label>API key</label><p class="help-block" style="margin-top:0">Opret nøglen i Flax Reseller Dashboard → API-dokumentation → Opret API-nøgle.</p>
                        <input class="form-control" type="password" name="api_key" autocomplete="new-password" placeholder="{{ $maskedKey ?: 'Indtast API key' }}">
                        @if($maskedKey)<p class="help-block">Gemt key: <code>{{ $maskedKey }}</code>. Lad feltet være tomt for at beholde den.</p>@endif
                    </div>
                    <label><input type="checkbox" name="enabled" value="1" {{ old('enabled',$settings->enabled ?? false) ? 'checked' : '' }}> Aktivér Flax VPS Reseller</label>
                </div>
                <div class="box-footer"><button class="btn btn-primary btn-block"><i class="fa fa-save"></i> Gem integration</button></div>
            </form>
            <div class="box-body" style="border-top:1px solid #26384d">
                <div class="row">
                    <div class="col-xs-6">
                        <form method="POST" action="{{ route('admin.flax.test') }}">@csrf<button class="btn btn-default btn-block"><i class="fa fa-plug"></i> Test forbindelse</button></form>
                    </div>
                    <div class="col-xs-6">
                        <form method="POST" action="{{ route('admin.flax.templates.sync') }}">@csrf<button class="btn btn-info btn-block"><i class="fa fa-refresh"></i> Synk OS templates</button></form>
                    </div>
                </div>
                @if($settings?->last_tested_at)
                    <p class="help-block" style="margin-top:12px">Senest testet: {{ \Carbon\Carbon::parse($settings->last_tested_at)->format('d.m.Y H:i') }} · {{ $settings->last_test_message }}</p>
                @endif
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-linux"></i> VirtFusion OS templates</h3><span class="badge pull-right">{{ $templates->count() }}</span></div>
            <div class="box-body" style="max-height:330px;overflow:auto">
                @forelse($templates as $template)
                    <div style="padding:9px 0;border-bottom:1px solid rgba(128,128,128,.15)">
                        <strong>{{ $template->label }}</strong>
                        <div class="text-muted" style="font-size:11px">Flax template #{{ $template->upstream_id }}</div>
                    </div>
                @empty
                    <p class="text-muted">Ingen templates endnu. Gem API-oplysninger og tryk “Synk OS templates”.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-plus-circle"></i> VPS-produkter</h3>
                <form method="POST" action="{{ route('admin.flax.products.import-presets') }}" class="pull-right" style="margin:0">
                    @csrf
                    <button class="btn btn-xs btn-info" type="submit" onclick="return confirm('Importér Flax VPS-01 til VPS-06+ med de viste upstream-priser? Eksisterende salgspriser overskrives ikke.')">
                        <i class="fa fa-download"></i> Importér Flax presets
                    </button>
                </form>
            </div>
            <form method="POST" action="{{ route('admin.flax.products.store') }}">@csrf
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-5"><div class="form-group"><label>Navn</label><input class="form-control" name="name" required placeholder="VPS Starter"></div></div>
                        <div class="col-sm-2"><div class="form-group"><label>Flax ID</label><input class="form-control" type="number" name="upstream_product_id" min="1" required></div></div>
                        <div class="col-sm-2"><div class="form-group"><label>Flax cost</label><input class="form-control" type="number" step=".01" name="upstream_monthly_price" placeholder="0.00"></div></div>
                        <div class="col-sm-3"><div class="form-group"><label>Din pris/md</label><input class="form-control" type="number" step=".01" name="monthly_price" required></div></div>
                    </div>
                    <div class="form-group"><label>Beskrivelse</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                    <div class="row">
                        <div class="col-sm-2"><label>vCPU</label><input class="form-control" type="number" name="vcpu"></div>
                        <div class="col-sm-2"><label>RAM MB</label><input class="form-control" type="number" name="memory_mb"></div>
                        <div class="col-sm-2"><label>Disk GB</label><input class="form-control" type="number" name="disk_gb"></div>
                        <div class="col-sm-2"><label>IPv4</label><input class="form-control" type="number" name="ipv4_count" value="1" required></div>
                        <div class="col-sm-2"><label>Sortering</label><input class="form-control" type="number" name="sort_order" value="0"></div>
                        <div class="col-sm-2"><label>Valuta</label><input class="form-control" name="currency" value="DKK" maxlength="3" required></div>
                    </div>
                    <div class="form-group" style="margin-top:10px"><label>Bandwidth</label><input class="form-control" name="bandwidth" placeholder="F.eks. 10 TB / md"></div>
                    <label><input type="checkbox" name="enabled" value="1" checked> Aktiv</label>&nbsp;&nbsp;
                    <label><input type="checkbox" name="featured" value="1"> Fremhævet</label>
                </div>
                <div class="box-footer"><button class="btn btn-success"><i class="fa fa-plus"></i> Opret VPS-produkt</button><a class="btn btn-default pull-right" target="_blank" href="{{ route('store.vps') }}">Åbn VPS Hosting</a></div>
            </form>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">VPS-produkter</h3></div>
            <div class="box-body">
                @forelse($products as $product)
                    <form method="POST" action="{{ route('admin.flax.products.update',$product->id) }}" style="padding:14px 0;border-bottom:1px solid rgba(128,128,128,.18)">
                        @csrf @method('PATCH')
                        <div class="row">
                            <div class="col-sm-4"><input class="form-control input-sm" name="name" value="{{ $product->name }}" required></div>
                            <div class="col-sm-2"><input class="form-control input-sm" type="number" name="upstream_product_id" value="{{ $product->upstream_product_id }}" required></div>
                            <div class="col-sm-2"><input class="form-control input-sm" type="number" step=".01" name="upstream_monthly_price" value="{{ $product->upstream_monthly_price }}" placeholder="Flax cost"></div>
                            <div class="col-sm-2"><input class="form-control input-sm" type="number" step=".01" name="monthly_price" value="{{ $product->monthly_price }}" required></div>
                            <div class="col-sm-2"><input class="form-control input-sm" name="currency" value="{{ $product->currency }}" required></div>
                        </div>
                        <input type="hidden" name="description" value="{{ $product->description }}">
                        <input type="hidden" name="memory_mb" value="{{ $product->memory_mb }}">
                        <input type="hidden" name="vcpu" value="{{ $product->vcpu }}">
                        <input type="hidden" name="disk_gb" value="{{ $product->disk_gb }}">
                        <input type="hidden" name="ipv4_count" value="{{ $product->ipv4_count }}">
                        <input type="hidden" name="bandwidth" value="{{ $product->bandwidth }}">
                        <input type="hidden" name="sort_order" value="{{ $product->sort_order }}">
                        <div style="margin-top:8px">
                            <label><input type="checkbox" name="enabled" value="1" {{ $product->enabled ? 'checked' : '' }}> Aktiv</label>&nbsp;&nbsp;
                            <label><input type="checkbox" name="featured" value="1" {{ $product->featured ? 'checked' : '' }}> Fremhævet</label>
                            <span class="text-muted" style="margin-left:12px">
                                {{ $product->vcpu ?: '—' }} vCPU · {{ $product->memory_mb ?: '—' }} MB · {{ $product->disk_gb ?: '—' }} GB
                                @if($product->upstream_monthly_price !== null)
                                    · Flax {{ number_format((float)$product->upstream_monthly_price,2,',','.') }} kr
                                    · Margin {{ number_format((float)$product->monthly_price - (float)$product->upstream_monthly_price,2,',','.') }} kr
                                @endif
                            </span>
                            <button class="btn btn-xs btn-primary pull-right">Gem</button>
                        </div>
                    </form>
                    @if(!DB::table('nodexa_flax_services')->where('product_id',$product->id)->exists())
                    <form method="POST" action="{{ route('admin.flax.products.delete',$product->id) }}" style="margin-top:-34px;margin-right:55px" class="pull-right">@csrf @method('DELETE')<button class="btn btn-xs btn-danger" onclick="return confirm('Slet VPS-produkt?')">Slet</button></form>
                    @endif
                @empty
                    <p class="text-muted">Ingen VPS-produkter er oprettet endnu.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="box">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-server"></i> Flax VPS-services</h3><span class="badge pull-right">{{ $services->count() }}</span></div>
    <div class="box-body table-responsive">
        <table class="table table-hover">
            <tr><th>#</th><th>Kunde</th><th>VPS</th><th>Upstream</th><th>Pris</th><th>Status</th><th>Næste faktura</th><th>Handling</th></tr>
            @forelse($services as $service)
                <tr>
                    <td>#{{ $service->id }}</td>
                    <td>{{ $service->user_email ?: $service->username }}</td>
                    <td><strong>{{ $service->hostname }}</strong><br><small>{{ $service->product_name }} @if($service->primary_ip)· {{ $service->primary_ip }}@endif</small></td>
                    <td>@if($service->upstream_server_id)<code>{{ $service->upstream_server_id }}</code>@else<span class="text-muted">Ikke provisioneret</span>@endif<br>@if($service->upstream_price !== null)<small>Flax cost: {{ number_format((float)$service->upstream_price,2,',','.') }}</small>@endif</td>
                    <td>{{ number_format((float)$service->retail_price,2,',','.') }} {{ $service->product_currency }}/md</td>
                    <td><span class="label {{ $service->status==='active'?'label-success':($service->status==='suspended'?'label-danger':($service->status==='provisioning_failed'?'label-danger':'label-warning')) }}">{{ $service->status }}</span>@if($service->last_error)<br><small class="text-danger">{{ IlluminateSupportStr::limit($service->last_error,90) }}</small>@endif</td>
                    <td>{{ $service->next_invoice_at ? CarbonCarbon::parse($service->next_invoice_at)->format('d.m.Y') : '—' }}</td>
                    <td style="min-width:190px">
                        <form method="POST" action="{{ route('admin.flax.services.action',$service->id) }}">@csrf
                            <select class="form-control input-sm" name="action" onchange="if(this.value==='terminate'&&!confirm('Terminate VPS hos Flax permanent?')){this.value='';return;} if(this.value)this.form.submit()">
                                <option value="">Handling…</option>
                                <option value="sync">Synk status</option>
                                @if(!$service->upstream_server_id)<option value="retry">Retry provisioning</option>@endif
                                @if($service->upstream_server_id)
                                <option value="boot">Start</option><option value="shutdown">Shutdown</option><option value="restart">Restart</option><option value="poweroff">Force poweroff</option>
                                @if($service->status==='suspended')<option value="unsuspend">Unsuspend</option>@else<option value="suspend">Suspend</option>@endif
                                <option value="terminate">Terminate</option>
                                @endif
                            </select>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-muted">Ingen VPS-services endnu.</td></tr>
            @endforelse
        </table>
    </div>
</div>
@endsection