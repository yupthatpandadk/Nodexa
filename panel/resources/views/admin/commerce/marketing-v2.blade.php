@extends('layouts.admin')
@section('title', 'Marketing')
@section('content')
<div class="container-fluid px-0">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="mb-4"><h1 class="h3 mb-1">Marketing</h1><p class="text-muted mb-0">Announcements og rabatkoder til Nodexa.</p></div>
<div class="row g-4">
<div class="col-12 col-xl-6"><div class="card h-100"><div class="card-body p-4">
<h2 class="h5">Nyt Announcement Banner</h2><p class="text-muted small">Vis en besked på storefront og bestillingssiden.</p>
<form method="POST" action="{{ route('admin.marketing.announcements.store') }}">@csrf
<div class="mb-3"><label class="form-label">Titel</label><input class="form-control" name="title" required></div>
<div class="mb-3"><label class="form-label">Besked</label><textarea class="form-control" name="message" rows="3" required></textarea></div>
<div class="row g-3"><div class="col-md-6"><label class="form-label">Type</label><select class="form-select" name="type"><option value="info">Info</option><option value="success">Success</option><option value="warning">Warning</option><option value="danger">Danger</option></select></div><div class="col-md-6"><label class="form-label">Knaptekst</label><input class="form-control" name="button_text"></div></div>
<div class="mt-3"><label class="form-label">Knap URL</label><input class="form-control" name="button_url"></div>
<div class="row g-3 mt-0"><div class="col-md-6"><label class="form-label">Start</label><input class="form-control" type="datetime-local" name="starts_at"></div><div class="col-md-6"><label class="form-label">Slut</label><input class="form-control" type="datetime-local" name="ends_at"></div></div>
<div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="active" value="1" checked id="announcement-active"><label class="form-check-label" for="announcement-active">Aktiv</label></div>
<button class="btn btn-primary w-100 mt-4">Opret announcement</button></form>
<hr class="my-4"><h3 class="h6">Announcements</h3>
@forelse($announcements as $item)<div class="border rounded p-3 mt-3"><div class="d-flex justify-content-between gap-2"><strong>{{ $item->title }}</strong><span class="badge {{ $item->active ? 'bg-success' : 'bg-secondary' }}">{{ $item->active ? 'Aktiv' : 'Deaktiveret' }}</span></div><div class="small text-muted mt-2">{{ $item->message }}</div><form class="mt-3" method="POST" action="{{ route('admin.marketing.announcements.delete',$item->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Slet</button></form></div>@empty<p class="text-muted small mt-3">Ingen announcements endnu.</p>@endforelse
</div></div></div>
<div class="col-12 col-xl-6"><div class="card h-100"><div class="card-body p-4">
<h2 class="h5">Ny rabatkode</h2><p class="text-muted small">Vælg om kunden får rabat i én måned eller permanent.</p>
<form method="POST" action="{{ route('admin.marketing.discounts.store') }}">@csrf
<div class="row g-3"><div class="col-md-6"><label class="form-label">Kode</label><input class="form-control text-uppercase" name="code" required placeholder="WELCOME20"></div><div class="col-md-6"><label class="form-label">Navn</label><input class="form-control" name="name" required placeholder="Velkomstrabat"></div></div>
<div class="row g-3 mt-0"><div class="col-md-6"><label class="form-label">Rabattype</label><select class="form-select" name="type"><option value="percent">Procent</option><option value="fixed">Fast beløb</option></select></div><div class="col-md-6"><label class="form-label">Værdi</label><input class="form-control" type="number" step="0.01" min="0.01" name="value" required></div></div>
<div class="mt-4 p-3 rounded border discount-duration-box"><label class="form-label fw-bold">Rabattens varighed</label><select class="form-select" name="duration" id="discount-duration" required><option value="once">1 måneds rabat</option><option value="forever">Permanent rabat</option></select><div class="form-text mt-2" id="duration-help"></div></div>
<div class="row g-3 mt-0"><div class="col-md-6"><label class="form-label">Minimumskøb</label><input class="form-control" type="number" step="0.01" min="0" name="minimum_amount"></div><div class="col-md-6"><label class="form-label">Maks. rabat</label><input class="form-control" type="number" step="0.01" min="0" name="maximum_discount"></div></div>
<div class="row g-3 mt-0"><div class="col-md-6"><label class="form-label">Brugsgrænse</label><input class="form-control" type="number" min="1" name="usage_limit"></div><div class="col-md-6"><label class="form-label">Pr. kunde</label><input class="form-control" type="number" min="1" name="per_user_limit"></div></div>
<div class="row g-3 mt-0"><div class="col-md-6"><label class="form-label">Gyldig fra</label><input class="form-control" type="datetime-local" name="starts_at"></div><div class="col-md-6"><label class="form-label">Gyldig til</label><input class="form-control" type="datetime-local" name="ends_at"></div></div>
<div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="new_customers_only" value="1" id="new-customers"><label class="form-check-label" for="new-customers">Kun nye kunder</label></div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="active" value="1" checked id="discount-active"><label class="form-check-label" for="discount-active">Aktiv</label></div>
@if($products->count())<div class="mt-3"><label class="form-label">Begræns til produkter</label><div class="border rounded p-3 product-list">@foreach($products as $product)<div class="form-check"><input class="form-check-input" type="checkbox" name="products[]" value="{{ $product->id }}" id="product-{{ $product->id }}"><label class="form-check-label" for="product-{{ $product->id }}">{{ $product->name }}</label></div>@endforeach</div></div>@endif
<button class="btn btn-primary w-100 mt-4">Opret rabatkode</button></form>
<hr class="my-4"><h3 class="h6">Rabatkoder</h3>
@forelse($discounts as $discount)<div class="border rounded p-3 mt-3"><div class="d-flex justify-content-between gap-2"><div><strong>{{ $discount->code }}</strong><div class="small text-muted">{{ $discount->name }}</div><div class="small mt-1"><span class="badge bg-primary">{{ $discount->duration === 'forever' ? 'Permanent rabat' : '1 måneds rabat' }}</span></div>@if($discount->duration !== 'forever')<div class="small text-muted mt-2">Efter første betalingsperiode betaler kunden det fulde beløb.</div>@endif</div><span class="badge {{ $discount->active ? 'bg-success' : 'bg-secondary' }}">{{ $discount->active ? 'Aktiv' : 'Deaktiveret' }}</span></div><form class="mt-3" method="POST" action="{{ route('admin.marketing.discounts.delete',$discount->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Slet</button></form></div>@empty<p class="text-muted small mt-3">Ingen rabatkoder endnu.</p>@endforelse
</div></div></div>
</div></div>
<style>.discount-duration-box{background:rgba(99,102,241,.08);border-color:rgba(129,140,248,.3)!important}.product-list{max-height:180px;overflow:auto}@media(max-width:767px){.card-body{padding:1rem!important}.form-control,.form-select,.btn{min-height:44px}}</style>
<script>document.addEventListener('DOMContentLoaded',function(){var s=document.getElementById('discount-duration'),h=document.getElementById('duration-help');if(!s||!h)return;function u(){h.innerHTML=s.value==='forever'?'<strong>Permanent:</strong> Rabatten fortsætter på alle efterfølgende betalingsperioder.':'<strong>1 måned:</strong> Rabatten gælder kun første betalingsperiode. Fra næste måned betaler kunden det fulde normale beløb.';}s.addEventListener('change',u);u();});</script>
@endsection
