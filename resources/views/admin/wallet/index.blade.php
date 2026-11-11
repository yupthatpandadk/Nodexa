@extends('layouts.admin')
@section('title','Nodexa Wallet')
@section('content-header')
<h1>Nodexa Wallet <small>Kreditsaldo, manuelle tilgodehavender og historik.</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Wallet</li></ol>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="box box-primary">
  <div class="box-header with-border">
    <h3 class="box-title"><i class="fa fa-sliders"></i> Wallet – hvilke produkter må betales med kredit?</h3>
    <span class="pull-right label {{ $walletAllowsVps ? 'label-success' : 'label-primary' }}">{{ $walletAllowsVps ? 'VPS + Pterodactyl' : 'Kun Pterodactyl' }}</span>
  </div>
  <form method="POST" action="{{ route('admin.wallet.settings') }}">
    @csrf
    <div class="box-body">
      <p class="text-muted" style="margin-bottom:16px">Vælg hvad kundernes Wallet-saldo må bruges til. Begrænsningen gælder også blandede kurve og ubetalte ordrer, og den kontrolleres på serveren før pengene reserveres eller trækkes.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px">
        <label style="cursor:pointer;display:block;border:1px solid #304057;border-radius:10px;padding:16px;background:#111b2b">
          <input type="radio" name="allow_vps" value="1" {{ old('allow_vps', $walletAllowsVps ? '1' : '0') === '1' ? 'checked' : '' }}>
          <strong style="margin-left:6px">Pterodactyl + VPS</strong>
          <span class="text-muted" style="display:block;margin:8px 0 0 20px;font-weight:normal">Wallet-kredit kan bruges til både gameservere og VPS-hosting.</span>
        </label>
        <label style="cursor:pointer;display:block;border:1px solid #304057;border-radius:10px;padding:16px;background:#111b2b">
          <input type="radio" name="allow_vps" value="0" {{ old('allow_vps', $walletAllowsVps ? '1' : '0') === '0' ? 'checked' : '' }}>
          <strong style="margin-left:6px">Kun Pterodactyl</strong>
          <span class="text-muted" style="display:block;margin:8px 0 0 20px;font-weight:normal">Wallet-kredit kan kun betale gameservere. VPS-delen betales med Stripe.</span>
        </label>
      </div>
      <p class="help-block" style="margin-top:13px">Eksisterende saldi ændres ikke. Stripe-betalinger og allerede reserverede beløb påvirkes ikke bagudrettet.</p>
    </div>
    <div class="box-footer text-right"><button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Gem Wallet-indstillinger</button></div>
  </form>
</div>
<div class="row">
  <div class="col-md-4">
    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-credit-card"></i> Tilføj kundekredit</h3></div>
      <form method="POST" action="{{ route('admin.wallet.credit') }}">
        @csrf
        <input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="box-body">
          <div class="form-group"><label>Kundens e-mail</label><input class="form-control" name="email" type="email" value="{{ old('email') }}" placeholder="kunde@eksempel.dk"></div>
          <div class="form-group"><label>Alternativt kunde-ID</label><input class="form-control" name="user_id" type="number" min="1" value="{{ old('user_id') }}" placeholder="F.eks. 24"></div>
          <p class="help-block">Angiv mindst én af de to. Hvis du angiver begge, skal de matche samme konto.</p>
          <div class="form-group"><label>Beløb (DKK)</label><input class="form-control" name="amount" type="text" inputmode="decimal" required placeholder="F.eks. 69,00" value="{{ old('amount') }}"></div>
          <div class="form-group"><label>Årsag (gemmes i historikken)</label><textarea class="form-control" name="reason" rows="3" maxlength="500" required>{{ old('reason') }}</textarea></div>
          <p class="help-block">Kredit er ikke en automatisk Stripe-refundering. Brug kun denne formular til godkendte, manuelle tilgodehavender.</p>
        </div>
        <div class="box-footer"><button class="btn btn-primary" type="submit">Tilføj Wallet-kredit</button></div>
      </form>
    </div>
    <div class="callout callout-info">
      <strong>Refusion af fejlordre</strong>
      <p>Åbn <a href="{{ route('admin.operations') }}">Operations → Samlede checkouts</a> og vælg kredit til Wallet på en betalt, fejlet ordre. Kontrollér service og kundens samtykke først.</p>
    </div>
  </div>
  <div class="col-md-8">
    <div class="box box-default">
      <div class="box-header with-border"><h3 class="box-title">Kundesaldi</h3></div>
      <div class="box-body table-responsive">
        <table class="table table-striped">
          <thead><tr><th>Kunde</th><th>Wallet-saldo</th><th>Opdateret</th></tr></thead>
          <tbody>
            @forelse($wallets as $wallet)
            <tr><td>#{{ $wallet->user_id }}<br><small>{{ $wallet->email ?: $wallet->username }}</small></td>
            <td><strong>{{ number_format($wallet->balance_cents/100,2,',','.') }} DKK</strong></td><td>{{ $wallet->updated_at }}</td></tr>
            @empty<tr><td colspan="3">Ingen wallets oprettet endnu.</td></tr>@endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="box box-default">
      <div class="box-header with-border"><h3 class="box-title">Seneste 100 transaktioner</h3></div>
      <div class="box-body table-responsive">
        <table class="table table-condensed">
          <thead><tr><th>Tid</th><th>Kunde</th><th>Type</th><th>Beløb</th><th>Årsag</th></tr></thead>
          <tbody>
          @forelse($transactions as $transaction)
          <tr><td>{{ $transaction->created_at }}</td><td>#{{ $transaction->user_id }}<br><small>{{ $transaction->email }}</small></td>
          <td>{{ $transaction->type }}</td>
          <td><strong class="{{ $transaction->amount_cents >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($transaction->amount_cents/100,2,',','.') }} DKK</strong></td>
          <td>{{ \Illuminate\Support\Str::limit($transaction->description,80) }}</td></tr>
          @empty<tr><td colspan="5">Ingen transaktioner.</td></tr>@endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
