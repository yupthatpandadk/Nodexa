@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'discord'])

@section('title', 'Discord Login')
@section('content-header')
<h1>Discord Login<small>Konfigurer Discord OAuth2 til Nodexa-kunder.</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li><a href="{{ route('admin.settings') }}">Settings</a></li><li class="active">Discord</li></ol>
@endsection

@section('content')
@yield('settings::nav')
<div class="row"><div class="col-xs-12"><div class="box box-primary">
<form method="POST" action="{{ route('admin.settings.discord.update') }}">
{!! csrf_field() !!}<input type="hidden" name="_method" value="PATCH">
<div class="box-header with-border"><h3 class="box-title">Discord OAuth2</h3></div>
<div class="box-body">
<div class="alert alert-info">Opret en OAuth2 application i Discord Developer Portal og tilføj Redirect URL'en nedenfor under OAuth2 Redirects. Client Secret bliver gemt i serverens <code>.env</code> og vises aldrig igen i adminpanelet.</div>
<div class="form-group"><label><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" {{ old('enabled', $enabled) ? 'checked' : '' }}> Aktivér “Log ind med Discord”</label></div>
<div class="row">
<div class="form-group col-md-6"><label>Discord Client ID</label><input class="form-control" name="client_id" value="{{ old('client_id', $clientId) }}" placeholder="123456789012345678"><p class="text-muted"><small>Application ID fra Discord Developer Portal.</small></p></div>
<div class="form-group col-md-6"><label>Discord Client Secret</label><input type="password" autocomplete="new-password" class="form-control" name="client_secret" value="" placeholder="{{ $hasSecret ? 'Secret er allerede gemt — lad feltet være tomt for at beholde det' : 'Indtast Client Secret' }}"><p class="text-muted"><small>{{ $hasSecret ? 'Der er allerede gemt en Client Secret.' : 'Der er endnu ikke gemt en Client Secret.' }}</small></p></div>
</div>
<div class="form-group"><label>Redirect URL</label><input class="form-control" type="url" name="redirect_uri" value="{{ old('redirect_uri', $redirectUri) }}" required><p class="text-muted"><small>Denne adresse skal være præcis den samme som Redirect URL i Discord.</small></p></div>
<div class="form-group"><label>Efter login</label><input class="form-control" name="after_login" value="{{ old('after_login', $afterLogin) }}" required><p class="text-muted"><small>Normalt <code>/client</code> for Nodexa Kundeområde.</small></p></div>
</div>
<div class="box-footer"><button class="btn btn-primary pull-right" type="submit"><i class="fa fa-save"></i> Gem Discord-indstillinger</button></div>
</form></div></div></div>
@endsection
