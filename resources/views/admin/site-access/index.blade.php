@extends('layouts.admin')

@section('title', 'Countdown & Maintenance')

@section('content-header')
    <h1>Countdown & Maintenance <small>Styr hvad besøgende ser, mens administratorer fortsat kan arbejde.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Website Access</li>
    </ol>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.site-access.update') }}">
    @csrf
    @method('PATCH')

    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-clock-o"></i> Countdown</h3>
                    <span class="pull-right label {{ $countdownEnabled ? 'label-success' : 'label-default' }}">
                        {{ $countdownEnabled ? 'AKTIV' : 'FRA' }}
                    </span>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label style="display:block">Vis countdown-side</label>
                        <label class="switch-inline">
                            <input type="hidden" name="countdown_enabled" value="0">
                            <input type="checkbox" name="countdown_enabled" value="1" {{ $countdownEnabled ? 'checked' : '' }}>
                            <span>Aktivér countdown for almindelige besøgende</span>
                        </label>
                        <p class="help-block">Brugere med permission <code>site_access.bypass_countdown</code> går direkte igennem.</p>
                    </div>
                    <div class="form-group">
                        <label>Titel</label>
                        <input class="form-control" name="countdown_title" maxlength="120" required value="{{ old('countdown_title', $countdownTitle) }}">
                    </div>
                    <div class="form-group">
                        <label>Besked</label>
                        <textarea class="form-control" name="countdown_message" rows="4" maxlength="500" required>{{ old('countdown_message', $countdownMessage) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Sluttidspunkt</label>
                        <input class="form-control" type="datetime-local" name="countdown_target" value="{{ old('countdown_target', $countdownTarget) }}">
                        <p class="help-block">Timezone: {{ $timezone }}. Hvis feltet er tomt, vises siden uden nedtællingstal.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-wrench"></i> Maintenance</h3>
                    <span class="pull-right label {{ $maintenanceEnabled ? 'label-warning' : 'label-default' }}">
                        {{ $maintenanceEnabled ? 'AKTIV' : 'FRA' }}
                    </span>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label style="display:block">Vis maintenance-side</label>
                        <label class="switch-inline">
                            <input type="hidden" name="maintenance_enabled" value="0">
                            <input type="checkbox" name="maintenance_enabled" value="1" {{ $maintenanceEnabled ? 'checked' : '' }}>
                            <span>Aktivér maintenance for almindelige besøgende</span>
                        </label>
                        <p class="help-block">Brugere med permission <code>site_access.bypass_maintenance</code> kan fortsat bruge hele hjemmesiden og kontrolpanelet.</p>
                    </div>
                    <div class="form-group">
                        <label>Titel</label>
                        <input class="form-control" name="maintenance_title" maxlength="120" required value="{{ old('maintenance_title', $maintenanceTitle) }}">
                    </div>
                    <div class="form-group">
                        <label>Besked</label>
                        <textarea class="form-control" name="maintenance_message" rows="4" maxlength="500" required>{{ old('maintenance_message', $maintenanceMessage) }}</textarea>
                    </div>
                    <div class="callout callout-info" style="margin-bottom:0">
                        <strong>Prioritet:</strong> Hvis både Maintenance og Countdown er aktiveret, vises Maintenance.
                        Login og Admin Area forbliver tilgængelige, så en administrator aldrig bliver låst ude af styringen.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box">
        <div class="box-body" style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
            <div>
                <strong>Gem website-status</strong>
                <div class="text-muted">Ændringen træder i kraft med det samme og kræver ingen restart.</div>
            </div>
            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Gem ændringer</button>
        </div>
    </div>
</form>
@endsection
