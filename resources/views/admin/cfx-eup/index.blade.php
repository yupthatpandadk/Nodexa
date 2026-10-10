@extends('layouts.admin')

@section('title','CFX EUP Keys')

@section('content-header')
<h1>CFX EUP Keys <small>Key pool, kundetildeling og månedlige abonnementer.</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">CFX EUP Keys</li></ol>
@endsection

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-key"></i> Tilføj EUP key</h3></div>
            <form method="POST" action="{{ route('admin.cfx-eup.keys.store') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group"><label>Label</label><input class="form-control" name="label" maxlength="120" placeholder="Fx EUP Key #12"></div>
                    <div class="form-group"><label>CFX EUP Key</label><textarea class="form-control" name="key" rows="4" required autocomplete="off" placeholder="Indsæt key her..."></textarea><p class="help-block">Keyen bliver krypteret med Nodexas app-key og gemmes ikke i klartekst i databasen.</p></div>
                    <div class="form-group"><label>Interne noter</label><textarea class="form-control" name="notes" rows="3" maxlength="2000"></textarea></div>
                </div>
                <div class="box-footer"><button class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Tilføj key til pool</button></div>
            </form>
        </div>

        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-gift"></i> Giv key direkte</h3></div>
            <form method="POST" action="{{ route('admin.cfx-eup.assign') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group"><label>Kunde</label><select class="form-control" name="user_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->email }} @if($user->name)· {{ $user->name }}@endif</option>@endforeach</select></div>
                    <div class="form-group"><label>Ledig key</label><select class="form-control" name="eup_key_id" required>@forelse($availableKeys as $key)<option value="{{ $key->id }}">#{{ $key->id }} · {{ $key->label ?: 'CFX EUP Key' }}</option>@empty<option value="">Ingen ledige keys</option>@endforelse</select></div>
                    <div class="form-group"><label>Service navn</label><input class="form-control" name="description" value="CFX EUP Key" required maxlength="180"></div>
                </div>
                <div class="box-footer"><button class="btn btn-success btn-block" {{ $availableKeys->isEmpty() ? 'disabled' : '' }}>Giv key uden abonnement</button></div>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-refresh"></i> Opret månedlig EUP ordre</h3></div>
            <form method="POST" action="{{ route('admin.cfx-eup.orders.store') }}">
                @csrf
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6"><div class="form-group"><label>Kunde</label><select class="form-control" name="user_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->email }} @if($user->name)· {{ $user->name }}@endif</option>@endforeach</select></div></div>
                        <div class="col-sm-6"><div class="form-group"><label>Key</label><select class="form-control" name="eup_key_id"><option value="">Auto-vælg første ledige</option>@foreach($availableKeys as $key)<option value="{{ $key->id }}">#{{ $key->id }} · {{ $key->label ?: 'CFX EUP Key' }}</option>@endforeach</select></div></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><div class="form-group"><label>Pris pr. måned</label><div class="input-group"><input class="form-control" type="number" name="monthly_price" step="0.01" min="0.01" value="99.00" required><span class="input-group-addon">DKK</span></div></div></div>
                        <div class="col-sm-6"><div class="form-group"><label>Valuta</label><input class="form-control" name="currency" value="DKK" maxlength="3" required></div></div>
                    </div>
                    <div class="form-group"><label>Service navn</label><input class="form-control" name="description" value="CFX EUP Key" required maxlength="180"></div>
                    <div class="callout callout-info" style="margin-bottom:0"><strong>Sådan virker det:</strong> Keyen reserveres med det samme, første faktura oprettes automatisk, og kunden kan først se keyen når fakturaen markeres betalt. Derefter faktureres abonnementet automatisk hver måned.</div>
                </div>
                <div class="box-footer"><button class="btn btn-info"><i class="fa fa-shopping-cart"></i> Opret ordre + første faktura</button></div>
            </form>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Key pool</h3><span class="pull-right text-muted">{{ $keys->where('status','available')->count() }} ledige / {{ $keys->count() }} total</span></div>
            <div class="box-body no-padding table-responsive">
                <table class="table table-hover" style="margin-bottom:0"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Tildelt</th><th>Fingerprint</th><th>Noter</th></tr></thead><tbody>
                @forelse($keys as $key)
                <tr><td>#{{ $key->id }}</td><td><strong>{{ $key->label ?: 'CFX EUP Key' }}</strong></td><td><span class="label {{ $key->status==='available'?'label-success':($key->status==='assigned'?'label-primary':($key->status==='suspended'?'label-danger':'label-warning')) }}">{{ strtoupper($key->status) }}</span></td><td>{{ $key->assigned_email ?: '—' }}</td><td><code>{{ substr($key->key_fingerprint,0,12) }}…</code></td><td>{{ $key->notes ?: '—' }}</td></tr>
                @empty<tr><td colspan="6" class="text-muted" style="padding:25px;text-align:center">Ingen EUP keys er tilføjet endnu.</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">EUP ordrer & abonnementer</h3></div>
            <div class="box-body no-padding table-responsive">
                <table class="table table-hover" style="margin-bottom:0"><thead><tr><th>ID</th><th>Kunde</th><th>Service</th><th>Pris</th><th>Status</th><th>Første faktura</th><th></th></tr></thead><tbody>
                @forelse($orders as $order)
                <tr>
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->user_email }}</td>
                    <td><strong>{{ $order->description }}</strong><br><small class="text-muted">Key #{{ $order->eup_key_id ?: '—' }}</small></td>
                    <td>{{ number_format($order->monthly_price,2,',','.') }} {{ $order->currency }}@if($order->subscription_id)<br><small>/ måned</small>@endif</td>
                    <td><span class="label {{ $order->status==='active'?'label-success':($order->status==='suspended'?'label-danger':($order->status==='cancelled'?'label-default':'label-warning')) }}">{{ strtoupper($order->status) }}</span></td>
                    <td>{{ $order->invoice_number ?: 'Direkte tildeling' }} @if($order->invoice_status)<span class="label label-default">{{ $order->invoice_status }}</span>@endif</td>
                    <td class="text-right">@if($order->status!=='cancelled')<form method="POST" action="{{ route('admin.cfx-eup.orders.cancel',$order->id) }}">@csrf @method('DELETE')<button class="btn btn-xs btn-danger" onclick="return confirm('Annullér ordren og frigiv keyen?')">Annullér</button></form>@endif</td>
                </tr>
                @empty<tr><td colspan="7" class="text-muted" style="padding:25px;text-align:center">Ingen EUP ordrer endnu.</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>
    </div>
</div>
@endsection
