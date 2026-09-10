@extends('layouts.admin')
@section('title','Betalingsmuligheder')
@section('content-header')<h1>Betalingsmuligheder <small>Konfigurer kundernes betalingsmetoder</small></h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{$errors->first()}}</div>@endif
<style>.nx-pay-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.nx-pay{background:#0a1417;border:1px solid rgba(66,233,166,.16);border-radius:12px;padding:18px}.nx-pay h3{margin:0 0 5px;display:flex;justify-content:space-between;align-items:center}.nx-pay p{color:#8ba09c;font-size:12px;min-height:34px}.nx-pill{font-size:10px;padding:5px 9px;border-radius:14px;background:#132b2c;color:#42e9a6}.nx-off{color:#8ba09c}.nx-pay label{margin-top:10px}.nx-pay .form-control{background:#071214;color:#edf7f5;border-color:rgba(66,233,166,.18)}.nx-note{background:#0a1417;border:1px solid rgba(66,233,166,.14);border-radius:10px;padding:14px;margin-bottom:16px;color:#8ba09c}@media(max-width:1000px){.nx-pay-grid{grid-template-columns:1fr}} </style>
<div class="nx-note"><i class="fa fa-lock"></i> Hemmelige API-nøgler gemmes krypteret. Et tomt nøglefelt beholder den allerede gemte værdi.</div>
<div class="nx-pay-grid">
@foreach(['stripe'=>['Stripe / kort','Kortbetaling via Stripe',['public_key'=>'Publishable key','secret_key'=>'Secret key','webhook_secret'=>'Webhook secret']], 'mobilepay'=>['MobilePay','MobilePay-betaling til danske kunder',['merchant_id'=>'Merchant / merchant serial','client_id'=>'Client ID','client_secret'=>'Client secret','webhook_secret'=>'Webhook secret']], 'paypal'=>['PayPal','Betaling via PayPal',['client_id'=>'Client ID','client_secret'=>'Client secret','webhook_secret'=>'Webhook ID / secret']]] as $gateway=>$cfg)
@php($saved=$gateways->firstWhere('gateway',$gateway))
<form class="nx-pay" method="POST" action="{{route('admin.billing.gateways.save',$gateway)}}">@csrf
<h3>{{$cfg[0]}} <span class="nx-pill {{$saved&&$saved->enabled?'':'nx-off'}}">{{$saved&&$saved->enabled?'AKTIV':'FRA'}}</span></h3><p>{{$cfg[1]}}</p>
<input type="hidden" name="name" value="{{$cfg[0]}}"><label><input type="checkbox" name="enabled" value="1" {{$saved&&$saved->enabled?'checked':''}}> Aktivér betalingsmetode</label><br><label><input type="checkbox" name="test_mode" value="1" {{!$saved||$saved->test_mode?'checked':''}}> Test / sandbox</label>
@foreach($cfg[2] as $field=>$label)<div><label>{{$label}}</label><input class="form-control" type="password" name="{{$field}}" autocomplete="new-password" placeholder="{{$saved&&$saved->$field?'Gemt – lad stå tom for at beholde':'Indtast '.$label}}"></div>@endforeach
<input type="hidden" name="sort_order" value="{{array_search($gateway,['stripe','mobilepay','paypal'])}}"><button class="btn btn-primary" style="margin-top:15px;width:100%"><i class="fa fa-save"></i> Gem {{$cfg[0]}}</button></form>
@endforeach
</div>
@endsection