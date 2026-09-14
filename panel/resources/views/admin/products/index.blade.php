@extends('layouts.admin')
@section('title','Produktregister')
@section('content-header')
<h1>Produktregister <small>Alle nuværende og tidligere servere og VPS'er</small></h1>
@endsection
@section('content')
<style>
.pr-card{background:#091416;border:1px solid rgba(66,233,166,.14);border-radius:12px;padding:18px;margin-bottom:16px}.pr-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.pr-stat strong{display:block;font-size:24px;color:#42e9a6}.pr-search{display:grid;grid-template-columns:1fr 170px 170px auto;gap:10px}.pr-code{font-family:monospace;color:#42e9a6;font-weight:700}.pr-muted{color:#8ba09c}.pr-badge{display:inline-block;padding:4px 8px;border-radius:12px;background:rgba(66,233,166,.1)}@media(max-width:767px){.pr-stats,.pr-search{grid-template-columns:1fr 1fr}.pr-search input{grid-column:1/-1}.table-responsive{overflow-x:auto}}
</style>
<div class="pr-stats">
 <div class="pr-card pr-stat"><span class="pr-muted">Alle produkter</span><strong>{{ $stats['total'] }}</strong></div>
 <div class="pr-card pr-stat"><span class="pr-muted">Aktive</span><strong>{{ $stats['active'] }}</strong></div>
 <div class="pr-card pr-stat"><span class="pr-muted">Servere</span><strong>{{ $stats['servers'] }}</strong></div>
 <div class="pr-card pr-stat"><span class="pr-muted">VPS'er</span><strong>{{ $stats['vps'] }}</strong></div>
</div>
<div class="pr-card">
 <form method="GET" class="pr-search">
  <input class="form-control" name="search" value="{{ $search }}" placeholder="Søg produkt-ID, server-ID, navn, e-mail, hostname...">
  <select class="form-control" name="type"><option value="">Alle typer</option><option value="server" {{ $type==='server'?'selected':'' }}>Server</option><option value="vps" {{ $type==='vps'?'selected':'' }}>VPS</option></select>
  <select class="form-control" name="status"><option value="">Alle statusser</option>@foreach(['active','running','suspended','pending','awaiting_payment','cancelled','terminated','deleted'] as $s)<option value="{{ $s }}" {{ $status===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select>
  <button class="btn btn-primary"><i class="fa fa-search"></i> Søg</button>
 </form>
</div>
<div class="pr-card table-responsive">
<table class="table">
<thead><tr><th>Produkt-ID</th><th>Type</th><th>Server / VPS</th><th>Kunde</th><th>E-mail</th><th>Server-ID</th><th>Status</th><th>Købt/oprettet</th></tr></thead>
<tbody>
@forelse($products as $product)
<tr>
 <td class="pr-code">{{ $product->product_code }}</td>
 <td><span class="pr-badge">{{ strtoupper($product->product_type) }}</span></td>
 <td>{{ $product->name ?: '—' }}</td>
 <td>{{ trim(($product->name_first ?? '').' '.($product->name_last ?? '')) ?: ($product->username ?: '—') }}</td>
 <td>{{ $product->email ?: '—' }}</td>
 <td><code>{{ $product->external_id ?: $product->source_id }}</code></td>
 <td>{{ ucfirst(str_replace('_',' ',$product->status ?: 'unknown')) }}</td>
 <td>{{ $product->purchased_at ?: '—' }}</td>
</tr>
@empty<tr><td colspan="8" class="text-center pr-muted">Ingen produkter fundet.</td></tr>@endforelse
</tbody></table>
{{ $products->links() }}
</div>
@endsection
