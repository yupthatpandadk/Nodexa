@extends('layouts.admin')
@section('title', 'Discord Bot Modules')
@section('content-header')
<h1>Discord Bot <small>Module Manager</small></h1>
@endsection
@section('content')
<style>
.mod-wrap{max-width:1180px;margin:auto}.mod-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.mod-head h2{margin:0;color:#fff}.mod-head p{margin:4px 0 0;color:#8298a2}.mod-back{border:1px solid rgba(125,211,252,.15);padding:9px 13px;border-radius:9px;color:#dce8ec;background:#0a1820}.mod-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.mod-card{background:linear-gradient(145deg,#0c202a,#091820);border:1px solid rgba(125,211,252,.12);border-radius:14px;padding:17px;display:flex;gap:14px;align-items:flex-start}.mod-icon{width:42px;height:42px;flex:0 0 42px;border-radius:11px;background:rgba(88,101,242,.13);color:#8490ff;display:flex;align-items:center;justify-content:center;font-size:17px}.mod-main{flex:1;min-width:0}.mod-title{display:flex;align-items:center;justify-content:space-between;gap:10px}.mod-title strong{color:#fff;font-size:14px}.mod-main p{color:#849ba5;font-size:11px;margin:6px 0 12px;line-height:1.45}.mod-switch{position:relative;width:43px;height:24px;margin:0}.mod-switch input{opacity:0;width:0;height:0}.mod-slider{position:absolute;inset:0;border-radius:99px;background:#263942;cursor:pointer}.mod-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;border-radius:50%;background:white;transition:.2s}.mod-switch input:checked+.mod-slider{background:#5865f2}.mod-switch input:checked+.mod-slider:before{transform:translateX(19px)}.mod-fields{display:grid;grid-template-columns:1fr 1fr;gap:8px}.mod-fields input,.mod-fields textarea{width:100%;background:#07151c;border:1px solid rgba(125,211,252,.13);border-radius:8px;color:#eef7fa;padding:8px;font-size:12px}.mod-fields textarea{grid-column:1/-1;min-height:62px;resize:vertical}.mod-save{margin-top:16px;text-align:right}.mod-save button{background:#5865f2!important;border:0!important;border-radius:9px!important;padding:10px 18px!important;font-weight:700;color:#fff!important}@media(max-width:760px){.mod-grid{grid-template-columns:1fr}.mod-fields{grid-template-columns:1fr}.mod-fields textarea{grid-column:auto}.mod-head{align-items:flex-start}.mod-card{padding:14px}}
</style>
<div class="mod-wrap"><div class="mod-head"><div><h2>Modules</h2><p>Aktivér kun de funktioner din Discord Bot skal bruge.</p></div><a class="mod-back" href="{{ route('admin.discord-bot') }}"><i class="fa fa-arrow-left"></i> Bot</a></div>
<form method="POST" action="{{ route('admin.discord-bot.modules.update') }}">{!! csrf_field() !!}{!! method_field('PATCH') !!}<div class="mod-grid">
@php
$items=[
'welcome'=>['fa-sign-in','Welcome / Goodbye','Send velkomst- og farvelbeskeder.',['channel_id'=>'Kanal ID','message'=>'Velkomstbesked']],
'auto_role'=>['fa-user-plus','Auto Role','Giv automatisk en rolle til nye medlemmer.',['role_id'=>'Rolle ID']],
'tickets'=>['fa-ticket','Tickets','Opret og administrér support tickets via Discord.',['category_id'=>'Kategori ID','staff_role_id'=>'Staff rolle ID']],
'moderation'=>['fa-shield','Moderation','Moderationskommandoer og logning af handlinger.',['log_channel_id'=>'Log kanal ID']],
'logs'=>['fa-list-alt','Logs','Log joins, leaves og Discord-hændelser.',['channel_id'=>'Log kanal ID']],
'server_status'=>['fa-server','Server Status','Vis status for Nodexa/game-servere i Discord.',['channel_id'=>'Status kanal ID']],
'announcements'=>['fa-bullhorn','Announcements','Send announcements fra Nodexa til Discord.',['channel_id'=>'Announcement kanal ID']],
'verification'=>['fa-check-circle','Verification','Verificér medlemmer og tildel en rolle.',['channel_id'=>'Kanal ID','role_id'=>'Verificeret rolle ID']],
'commands'=>['fa-terminal','Commands','Aktivér bot slash-commands.',['prefix'=>'Fallback prefix']]
];
@endphp
@foreach($items as $key=>$item) @php $cfg=$modules[$key]??[]; @endphp
<div class="mod-card"><div class="mod-icon"><i class="fa {{ $item[0] }}"></i></div><div class="mod-main"><div class="mod-title"><strong>{{ $item[1] }}</strong><label class="mod-switch"><input type="checkbox" name="modules[{{ $key }}][enabled]" value="1" {{ !empty($cfg['enabled'])?'checked':'' }}><span class="mod-slider"></span></label></div><p>{{ $item[2] }}</p><div class="mod-fields">@foreach($item[3] as $field=>$placeholder) @if($field==='message')<textarea name="modules[{{ $key }}][{{ $field }}]" placeholder="{{ $placeholder }}">{{ $cfg[$field]??'' }}</textarea>@else<input name="modules[{{ $key }}][{{ $field }}]" value="{{ $cfg[$field]??'' }}" placeholder="{{ $placeholder }}">@endif @endforeach</div></div></div>
@endforeach
</div><div class="mod-save"><button class="btn" type="submit"><i class="fa fa-save"></i> Gem modules</button></div></form></div>
@endsection
