@extends('layouts.admin')
@section('title','Discord Bot Modules')
@section('content-header')<h1>Discord Bot <small>Module Manager</small></h1>@endsection
@section('content')
@php
$categoryOrder=['Core','Moderation','Community','Automation','Engagement','Voice','Integrations','Utilities'];
$groups=[];
foreach($definitions as $key=>$item){$category=$item['category']??'Core';$groups[$category][$key]=$item;}
uksort($groups,function($a,$b)use($categoryOrder){$ai=array_search($a,$categoryOrder,true);$bi=array_search($b,$categoryOrder,true);$ai=$ai===false?999:$ai;$bi=$bi===false?999:$bi;return $ai<=>$bi;});
$enabledCount=collect($definitions)->filter(fn($item,$key)=>!empty(($modules[$key]??[])['enabled']))->count();
@endphp
<style>
.mod-wrap{max-width:1220px;margin:auto}.mod-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:16px}.mod-head h2{margin:0;color:#fff}.mod-head p{margin:5px 0 0;color:#8298a2}.mod-back{border:1px solid rgba(125,211,252,.15);padding:9px 13px;border-radius:9px;color:#dce8ec;background:#0a1820}.mod-overview{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px}.mod-pill{padding:7px 10px;border:1px solid rgba(125,211,252,.12);border-radius:999px;background:#0b1c26;color:#91a7b0;font-size:11px}.mod-pill strong{color:#fff}.mod-section{margin:0 0 25px}.mod-section-head{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:10px;padding:0 2px}.mod-section-head h3{margin:0;color:#eaf4f7;font-size:14px;text-transform:uppercase;letter-spacing:.08em}.mod-section-head span{font-size:10px;color:#657f8a}.mod-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mod-card{background:linear-gradient(145deg,#0c202a,#091820);border:1px solid rgba(125,211,252,.12);border-radius:14px;padding:15px;display:flex;gap:13px;align-items:center;cursor:pointer;transition:.18s}.mod-card:hover{border-color:var(--nodexa-accent,#5865f2);transform:translateY(-1px);background:linear-gradient(145deg,#102632,#091820)}.mod-icon{width:42px;height:42px;flex:0 0 42px;border-radius:11px;background:color-mix(in srgb,var(--nodexa-accent,#5865f2) 14%,transparent);color:var(--nodexa-accent,#8490ff);display:flex;align-items:center;justify-content:center;font-size:17px}.mod-main{flex:1;min-width:0}.mod-title{display:flex;align-items:center;justify-content:space-between;gap:10px}.mod-title strong{color:#fff;font-size:13px}.mod-main p{color:#849ba5;font-size:10.5px;line-height:1.45;margin:5px 0 0}.mod-right{display:flex;align-items:center;gap:10px}.mod-state{font-size:9px;font-weight:700;padding:5px 8px;border-radius:99px}.on{color:#57d68d;background:rgba(35,165,90,.12)}.off{color:#9aabb2;background:rgba(148,163,184,.08)}.mod-arrow{color:#637d88;font-size:13px}.mod-note{margin-top:8px;color:#718993;font-size:11px;text-align:right}@media(max-width:760px){.mod-grid{grid-template-columns:1fr}.mod-card{padding:13px}.mod-head{align-items:flex-start}.mod-state{display:none}.mod-section{margin-bottom:22px}}
</style>
<div class="mod-wrap">
 <div class="mod-head"><div><h2>Modules</h2><p>Byg botten præcis som du vil. Hvert modul kan konfigureres individuelt.</p></div><a class="mod-back" href="{{ route('admin.discord-bot') }}"><i class="fa fa-arrow-left"></i> Bot</a></div>
 <div class="mod-overview"><span class="mod-pill"><strong>{{ count($definitions) }}</strong> moduler</span><span class="mod-pill"><strong>{{ $enabledCount }}</strong> aktive</span><span class="mod-pill">Modulær arkitektur</span></div>
 @foreach($groups as $category=>$items)
 <section class="mod-section">
  <div class="mod-section-head"><h3>{{ $category }}</h3><span>{{ count($items) }} moduler</span></div>
  <div class="mod-grid">
   @foreach($items as $key=>$item) @php $cfg=$modules[$key]??[]; @endphp
   <div class="mod-card" role="link" tabindex="0" onclick="window.location='{{ route('admin.discord-bot.modules.module',['module'=>$key]) }}'" onkeydown="if(event.key==='Enter')window.location='{{ route('admin.discord-bot.modules.module',['module'=>$key]) }}'">
    <div class="mod-icon"><i class="fa {{ $item['icon'] }}"></i></div><div class="mod-main"><div class="mod-title"><strong>{{ $item['title'] }}</strong></div><p>{{ $item['description'] }}</p></div><div class="mod-right"><span class="mod-state {{ !empty($cfg['enabled'])?'on':'off' }}">{{ !empty($cfg['enabled'])?'Aktiv':'Deaktiveret' }}</span><i class="fa fa-chevron-right mod-arrow"></i></div>
   </div>
   @endforeach
  </div>
 </section>
 @endforeach
 <div class="mod-note"><i class="fa fa-info-circle"></i> Aktivering og indstillinger administreres inde på hvert modul.</div>
</div>
@endsection
