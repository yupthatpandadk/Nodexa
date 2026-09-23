@extends('layouts.admin')
@section('title','Error Center')
@section('content-header')<h1>Error Center <small>Diagnostik, årsag og sikre forslag til rettelser</small></h1>@endsection
@section('content')
<div class="row">
 <div class="col-md-8">
  <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-stethoscope"></i> System diagnostics</h3><span class="pull-right text-muted">Live check</span></div><div class="box-body">
   @foreach($checks as $c)<div style="border:1px solid #26364a;border-radius:8px;padding:14px;margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;gap:12px"><strong>{{$c['name']}}</strong><span class="label label-{{$c['status']==='ok'?'success':'danger'}}">{{$c['status']==='ok'?'ONLINE / OK':'ERROR'}}</span></div>
    <p style="margin:8px 0 0">{{$c['detail']}}</p>
    @if($c['fix'])<div class="callout callout-warning" style="margin:10px 0 0"><b>Muligt fix:</b> {{$c['fix']}}</div>@endif
   </div>@endforeach
  </div></div>
  <div class="box"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bug"></i> Seneste registrerede fejl</h3><span class="badge pull-right">{{count($logs)}}</span></div><div class="box-body">
   @forelse($logs as $e)<details style="border-bottom:1px solid #26364a;padding:12px 0"><summary style="cursor:pointer"><span class="label label-danger">{{$e['level']}}</span> <b>{{$e['message']}}</b><small class="text-muted pull-right">{{$e['time']}}</small></summary><div style="padding:12px 5px"><p><b>Sandsynlig årsag</b><br>{{$e['reason']}}</p><p><b>Forslag til fix</b><br>{{$e['fix']}}</p><p class="text-muted"><i class="fa fa-info-circle"></i> Forslaget er diagnostisk hjælp og køres ikke automatisk.</p></div></details>@empty<p class="text-muted text-center" style="padding:25px">Ingen nyere Laravel ERROR/CRITICAL poster fundet.</p>@endforelse
  </div></div>
 </div>
 <div class="col-md-4">
  <div class="box box-info"><div class="box-header"><h3 class="box-title">Quick repair</h3></div><div class="box-body"><p>Kun sikre vedligeholdelseshandlinger kan køres automatisk.</p><form method="POST" action="{{route('admin.errors.repair')}}">@csrf<input type="hidden" name="action" value="clear_cache"><button class="btn btn-primary btn-block"><i class="fa fa-refresh"></i> Clear Laravel cache</button></form></div></div>
  <div class="box"><div class="box-header"><h3 class="box-title">Hvad kontrolleres?</h3></div><div class="box-body"><p><i class="fa fa-database"></i> Database</p><p><i class="fa fa-folder-open"></i> Storage/cache permissions</p><p><i class="fa fa-sitemap"></i> Alle Wings nodes</p><p><i class="fa fa-file-text-o"></i> Laravel error log</p></div></div>
 </div>
</div>
@endsection