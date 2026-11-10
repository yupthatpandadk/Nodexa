@extends('layouts.admin')
@section('title', 'Pterodactyl Egg-pakker')
@section('content-header')
<h1>Egg-pakker <small>Serverpakker til Nodexa Hosting</small></h1>
@endsection
@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row">
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cubes"></i> Opret serverpakker</h3>
                <a href="{{ route('admin.store') }}" class="btn btn-default btn-xs pull-right">Tilbage til Storefront</a>
            </div>
            <form method="POST" action="{{ route('admin.store.egg-packages.generate') }}">
                @csrf
                <div class="box-body">
                    <p class="text-muted">Vælg et installeret Pterodactyl Egg. Nodexa opretter Starter, Plus, Pro og Ultimate som <strong>skjulte produkter</strong>, som du selv kan godkende og aktivere.</p>
                    <div class="form-group">
                        <label for="package-egg">Installeret Egg</label>
                        <select id="package-egg" name="egg_id" class="form-control" required>
                            <option value="">Vælg Egg...</option>
                            @foreach($eggs as $egg)
                                <option value="{{ $egg->id }}" {{ (string) old('egg_id') === (string) $egg->id ? 'selected' : '' }}>
                                    {{ $egg->name }} ({{ $egg->nest ? $egg->nest->name : 'Ukendt Nest' }}) #{{ $egg->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="package-category">Spiltype</label>
                        <select id="package-category" name="category" class="form-control" required>
                            <option value="auto" {{ old('category', 'auto') === 'auto' ? 'selected' : '' }}>Automatisk ud fra Egg</option>
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="help-block">Lad prisfeltet være tomt for den foreslåede pris. Se ressourcer og prisforslag i oversigten nedenfor.</p>
                    <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead><tr><th>Pakke</th><th>Pris (DKK/md.)</th></tr></thead>
                            <tbody>
                            @foreach($tiers as $tier => $label)
                                <tr>
                                    <td>
                                        <label style="font-weight:600">
                                            <input type="checkbox" name="tiers[]" value="{{ $tier }}" {{ in_array($tier, old('tiers', array_keys($tiers))) ? 'checked' : '' }}>
                                            {{ $label }}
                                        </label>
                                    </td>
                                    <td>
                                        <input class="form-control input-sm" type="number"
                                               name="prices[{{ $tier }}]" value="{{ old('prices.'.$tier) }}"
                                               placeholder="Forslag" step="0.01" min="0.01" max="99999"
                                               aria-label="Pris for {{ $label }}">
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-block" {{ $eggs->isEmpty() ? 'disabled' : '' }}>
                        <i class="fa fa-plus-circle"></i> Opret skjulte pakker
                    </button>
                </div>
            </form>
        </div>
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">Vejledende pakkeprofiler</h3></div>
            <div class="box-body">
                <p class="text-muted">RAM, CPU, disk og priser varierer efter spiltype. Kontrollér økonomi og tilgængelig nodekapacitet før offentliggørelse.</p>
                <div class="table-responsive">
                    <table class="table table-condensed table-striped">
                        <thead><tr><th>Spiltype</th><th>Niveau</th><th>RAM</th><th>CPU</th><th>DKK/md.</th></tr></thead>
                        <tbody>
                        @foreach($profiles as $category => $plans)
                            @foreach($plans as $tier => $plan)
                                <tr>
                                    <td>{{ $categories[$category] }}</td>
                                    <td>{{ $tiers[$tier] }}</td>
                                    <td>{{ number_format($plan['memory'] / 1024, 1, ',', '.') }} GB</td>
                                    <td>{{ $plan['cpu'] }}%</td>
                                    <td>{{ number_format($plan['price'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-list"></i> Produkter og pakker</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Produkt</th><th>Egg</th><th>Pris</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td><strong>{{ $product->name }}</strong><br><small>{{ $product->game }}</small></td>
                            <td>{{ $product->egg_id }}</td>
                            <td>{{ number_format((float) $product->price_monthly, 2, ',', '.') }} kr.</td>
                            <td>{{ $product->enabled ? 'Aktiv' : 'Skjult' }}</td>
                            <td>
                                <details>
                                    <summary style="cursor:pointer;color:#218cc2">Redigér</summary>
                                    <form method="POST" action="{{ route('admin.store.products.details', $product) }}" style="min-width:225px;padding-top:10px">
                                        @csrf
                                        @method('PATCH')
                                        <div class="form-group"><label>Navn<input class="form-control input-sm" name="name" required maxlength="100" value="{{ $product->name }}"></label></div>
                                        <div class="form-group"><label>Kategori<input class="form-control input-sm" name="game" required maxlength="80" value="{{ $product->game }}"></label></div>
                                        <div class="form-group"><label>Beskrivelse<textarea class="form-control input-sm" name="description" maxlength="2000" rows="2">{{ $product->description }}</textarea></label></div>
                                        <div class="form-group"><label>Pris DKK/md.<input type="number" step=".01" min="0" max="99999" class="form-control input-sm" name="price_monthly" value="{{ $product->price_monthly }}" required></label></div>
                                        @foreach(['memory'=>'RAM (MB)','cpu'=>'CPU (%)','disk'=>'Disk (MB)','databases'=>'Databaser','backups'=>'Backups','allocations'=>'Allocations'] as $field => $label)
                                            <div class="form-group"><label>{{ $label }}
                                                <input type="number" class="form-control input-sm" name="{{ $field }}" min="{{ $field === 'memory' ? 128 : ($field === 'cpu' ? 10 : ($field === 'disk' ? 256 : ($field === 'allocations' ? 1 : 0))) }}" value="{{ $product->$field }}" required>
                                            </label></div>
                                        @endforeach
                                        <button class="btn btn-primary btn-sm" type="submit">Gem pakke</button>
                                        <p class="help-block">Ændringer gælder nye bestillinger.</p>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Ingen produkter endnu.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
