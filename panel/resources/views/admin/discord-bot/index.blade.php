@extends('layouts.admin')
@section('title', 'Discord Bot')
@section('content-header')
    <h1>Discord Bot <small>Konfiguration og forbindelsesstatus</small></h1>
@endsection
@section('content')
<div class="row">
    <div class="col-md-8">
        <form action="{{ route('admin.discord-bot.update') }}" method="POST">
            {!! csrf_field() !!}{!! method_field('PATCH') !!}
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-discord"></i> Bot Configuration</h3></div>
                <div class="box-body">
                    <div class="form-group"><label><input type="checkbox" name="enabled" value="1" {{ !empty($bot['enabled']) ? 'checked' : '' }}> Aktivér Discord Bot</label><p class="text-muted small">Aktiverer bot-integration i Nodexa.</p></div>
                    <div class="form-group"><label>Bot Token</label><input type="password" class="form-control" name="token" autocomplete="new-password" placeholder="{{ $hasToken ? 'Token er gemt — udfyld kun for at ændre det' : 'Indsæt Discord Bot Token' }}"><p class="text-muted small">Tokenet gemmes i serverens .env og vises ikke igen.</p></div>
                    <div class="row"><div class="col-md-6"><div class="form-group"><label>Client / Application ID</label><input class="form-control" name="client_id" value="{{ old('client_id', $bot['client_id'] ?? '') }}"></div></div><div class="col-md-6"><div class="form-group"><label>Guild / Server ID</label><input class="form-control" name="guild_id" value="{{ old('guild_id', $bot['guild_id'] ?? '') }}"></div></div></div>
                    <div class="row"><div class="col-md-6"><div class="form-group"><label>Status Channel ID</label><input class="form-control" name="status_channel_id" value="{{ old('status_channel_id', $bot['status_channel_id'] ?? '') }}" placeholder="Valgfri"></div></div><div class="col-md-6"><div class="form-group"><label>Automatisk rolle ID</label><input class="form-control" name="auto_role_id" value="{{ old('auto_role_id', $bot['auto_role_id'] ?? '') }}" placeholder="Valgfri"></div></div></div>
                    <div class="form-group"><label>Bot Status / Presence</label><input class="form-control" name="presence" maxlength="128" value="{{ old('presence', $bot['presence'] ?? 'Nodexa Hosting') }}" placeholder="Nodexa Hosting"></div>
                </div>
                <div class="box-footer"><button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Gem indstillinger</button></div>
            </div>
        </form>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Forbindelse</h3></div>
            <div class="box-body text-center">
                @if($status['avatar'])<img src="{{ $status['avatar'] }}" alt="Bot avatar" style="width:72px;height:72px;border-radius:50%;margin-bottom:12px">@endif
                <h4>{{ $status['name'] ?: 'Discord Bot' }}</h4>
                @if($status['connected'])<p><span class="label label-success">Forbundet</span></p>@elseif(!empty($bot['enabled']))<p><span class="label label-danger">Ikke forbundet</span></p>@else<p><span class="label label-default">Deaktiveret</span></p>@endif
                @if($status['guild'])<p class="text-muted">Server: {{ $status['guild'] }}</p>@endif
                @if($status['error'])<p class="text-danger small">{{ $status['error'] }}</p>@endif
                <form action="{{ route('admin.discord-bot.test') }}" method="POST">{!! csrf_field() !!}<button class="btn btn-default btn-block" type="submit"><i class="fa fa-plug"></i> Test forbindelse</button></form>
            </div>
        </div>
        <div class="box"><div class="box-header with-border"><h3 class="box-title">Bot funktioner</h3></div><div class="box-body"><p><i class="fa fa-check text-green"></i> Sikker token-lagring</p><p><i class="fa fa-check text-green"></i> Discord API-status</p><p><i class="fa fa-check text-green"></i> Guild-konfiguration</p><p><i class="fa fa-check text-green"></i> Statuskanal</p><p><i class="fa fa-check text-green"></i> Auto-role konfiguration</p></div></div>
    </div>
</div>
@endsection
