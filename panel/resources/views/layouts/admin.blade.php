<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{config('app.name','Nodexa')}} · @yield('title')</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="_token" content="{{csrf_token()}}">
@include('layouts.scripts')
@include('partials.nodexa-theme')
@section('scripts')
{!!Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}')!!}
{!!Theme::css('vendor/adminlte/admin.min.css?t={cache-version}')!!}
{!!Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}')!!}
{!!Theme::css('css/pterodactyl.css?t={cache-version}')!!}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="{{asset('css/nodexa-ultimate.css')}}?v={{$appVersion}}">
<link rel="stylesheet" href="{{asset('css/nodexa-admin-shell.css')}}?v={{$appVersion}}">
@show
</head>
<body class="hold-transition skin-blue fixed sidebar-mini">
<div class="wrapper">
<header class="main-header"><a href="{{route('admin.index')}}" class="logo">Nodexa</a><nav class="navbar navbar-static-top"><a href="#" class="sidebar-toggle" id="nodexaMobileMenu" aria-label="Åbn menu" aria-expanded="false"><span class="sr-only">Menu</span></a><div class="navbar-custom-menu"><ul class="nav navbar-nav"><li><a href="{{route('admin.notifications')}}" title="Notifikationer"><i class="fa fa-bell-o"></i></a></li><li><a href="{{route('client.area')}}" title="Client Area"><i class="fa fa-user-circle-o"></i></a></li><li><a href="{{route('auth.logout')}}" title="Log ud"><i class="fa fa-sign-out"></i></a></li></ul></div></nav></header>
<aside class="main-sidebar"><section class="sidebar"><ul class="sidebar-menu">
<li class="header">OVERSIGT</li><li class="{{Route::currentRouteName()==='admin.index'?'active':''}}"><a href="{{route('admin.index')}}"><i class="fa fa-th-large"></i><span>Dashboard</span></a></li><li><a href="{{route('client.area')}}"><i class="fa fa-user-circle"></i><span>Client Area</span></a></li>
<li class="header">FORRETNING</li><li><a href="{{route('admin.analytics')}}"><i class="fa fa-line-chart"></i><span>Analytics</span></a></li><li><a href="{{route('admin.notifications')}}"><i class="fa fa-bell"></i><span>Notification Center</span></a></li><li><a href="{{route('admin.billing')}}"><i class="fa fa-file-text-o"></i><span>Billing</span></a></li><li><a href="{{route('admin.orders')}}"><i class="fa fa-shopping-bag"></i><span>Ordrer</span></a></li><li><a href="{{route('admin.products')}}"><i class="fa fa-cubes"></i><span>Produkter</span></a></li><li><a href="{{route('admin.payments')}}"><i class="fa fa-credit-card"></i><span>Betaling</span></a></li><li><a href="{{route('admin.marketing')}}"><i class="fa fa-bullhorn"></i><span>Marketing</span></a></li><li><a href="{{route('admin.tickets')}}"><i class="fa fa-ticket"></i><span>Support</span></a></li><li><a href="{{route('admin.users')}}"><i class="fa fa-users"></i><span>Kunder & brugere</span></a></li>
<li class="header">INFRASTRUKTUR</li><li><a href="{{route('admin.status-center')}}"><i class="fa fa-heartbeat"></i><span>Status Center</span></a></li><li><a href="{{route('admin.vps')}}"><i class="fa fa-cloud"></i><span>VPS Hosting</span></a></li><li><a href="{{route('admin.servers')}}"><i class="fa fa-server"></i><span>Game Servers</span></a></li><li><a href="{{route('admin.nodes')}}"><i class="fa fa-sitemap"></i><span>Nodes</span></a></li><li><a href="{{route('admin.databases')}}"><i class="fa fa-database"></i><span>Databaser</span></a></li>
<li class="header">PLATFORM</li><li><a href="{{route('admin.security-center')}}"><i class="fa fa-shield"></i><span>Security Center</span></a></li><li><a href="{{route('admin.discord-bot')}}"><i class="fa fa-comments"></i><span>Discord Bot</span></a></li><li><a href="{{route('admin.settings')}}"><i class="fa fa-sliders"></i><span>Indstillinger</span></a></li><li><a href="{{route('admin.updates')}}"><i class="fa fa-cloud-download"></i><span>Update Center</span></a></li><li><a href="{{route('admin.diagnostics')}}"><i class="fa fa-stethoscope"></i><span>Diagnostics</span></a></li><li><a href="{{route('admin.addons')}}"><i class="fa fa-puzzle-piece"></i><span>Addons</span></a></li>
</ul><div class="nx-admin-version">NODEXA PLATFORM<br><b>{{$appVersion}}</b></div></section></aside>
<div class="nx-sidebar-backdrop" id="nodexaSidebarBackdrop"></div>
<div class="content-wrapper"><section class="content-header">@yield('content-header')</section><section class="content">
@if(count($errors)>0)<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{$error}}</div>@endforeach</div>@endif
@php
$nxSeenAlerts = [];
@endphp
@foreach(Alert::getMessages() as $type=>$messages)
@foreach($messages as $message)
@php
$nxAlertText = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $message)));
$nxAlertKey = md5(strtolower($nxAlertText));
@endphp
@if(!isset($nxSeenAlerts[$nxAlertKey]))
@php
$nxSeenAlerts[$nxAlertKey] = true;
$nxCommitMatch = [];
if (stripos($nxAlertText, 'Nodexa-opdatering er tilgængelig') !== false) {
    preg_match('/GitHub\s+([0-9a-f]{7,40})/i', $nxAlertText, $nxCommitMatch);
}
@endphp
@if(stripos($nxAlertText, 'Nodexa-opdatering er tilgængelig') !== false)
<div class="nx-update-banner" role="status" aria-label="Ny Nodexa-opdatering">
<div class="nx-update-icon"><i class="fa fa-cloud-download"></i></div>
<div class="nx-update-copy"><div class="nx-update-eyebrow"><i class="fa fa-bolt"></i> Update klar</div><h3 class="nx-update-title">En ny Nodexa-version er klar</h3><div class="nx-update-meta">Opdatér platformen for at få de nyeste funktioner, forbedringer og fejlrettelser.</div><div class="nx-update-features"><span><i class="fa fa-check-circle"></i> Nye funktioner</span><span><i class="fa fa-shield"></i> Forbedringer</span><span><i class="fa fa-wrench"></i> Fejlrettelser</span></div></div>
<div class="nx-update-side"><div class="nx-update-version"><small>Installeret</small><strong>{{$appVersion}}</strong></div><div class="nx-update-arrow"><i class="fa fa-long-arrow-right"></i></div><div class="nx-update-version is-new"><small>Tilgængelig</small><strong>Ny version</strong></div><div class="nx-update-actions"><a class="btn btn-primary" href="{{route('admin.updates')}}"><i class="fa fa-cloud-download"></i> Se opdatering</a><a class="nx-change-link" href="{{route('admin.updates')}}"><i class="fa fa-list-alt"></i> Se changelog@if(!empty($nxCommitMatch[1])) · {{substr($nxCommitMatch[1],0,8)}}@endif</a></div></div>
</div>
@else
<div class="alert alert-{{$type}}">{{$message}}</div>
@endif
@endif
@endforeach
@endforeach
@yield('content')</section></div>
<footer class="main-footer"><strong>Nodexa Platform</strong> · Hosting Operations<div class="pull-right">{{$appVersion}}</div></footer>
</div>
@section('footer-scripts')
{!!Theme::js('vendor/jquery/jquery.min.js?t={cache-version}')!!}{!!Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}')!!}{!!Theme::js('vendor/adminlte/app.min.js?t={cache-version}')!!}
<script>(function(){var b=document.getElementById('nodexaMobileMenu'),d=document.getElementById('nodexaSidebarBackdrop');function setMenu(open){document.body.classList.toggle('nodexa-sidebar-open',open);document.body.classList.remove('sidebar-open');if(b)b.setAttribute('aria-expanded',open?'true':'false')}if(b)b.addEventListener('click',function(e){if(innerWidth<=991){e.preventDefault();e.stopPropagation();setMenu(!document.body.classList.contains('nodexa-sidebar-open'))}});if(d)d.addEventListener('click',function(){setMenu(false)});document.querySelectorAll('.main-sidebar a').forEach(function(a){a.addEventListener('click',function(){if(innerWidth<=991)setMenu(false)})});document.addEventListener('keydown',function(e){if(e.key==='Escape')setMenu(false)});window.addEventListener('resize',function(){if(innerWidth>991)setMenu(false)})})();</script>
@show
</body></html>