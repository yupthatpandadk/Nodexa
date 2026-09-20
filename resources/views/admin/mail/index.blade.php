@extends('layouts.admin')

@section('title') Mail Center @endsection

@section('content-header')
<h1>Mail Center <small>Send professional Nodexa emails from one place.</small></h1>
<ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Mail Center</li></ol>
@endsection

@section('content')
<div class="row">
 <div class="col-md-8">
  <div class="box box-primary">
   <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-paper-plane"></i> Compose email</h3></div>
   <form method="POST" action="{{ route('admin.mail.send') }}">
    {!! csrf_field() !!}
    <div class="box-body">
     <div class="row">
      <div class="form-group col-md-6"><label>Recipients</label>
       <select class="form-control" name="audience" id="audience">
        <option value="single">One customer</option><option value="admins">Administrators</option><option value="all">All customers</option>
       </select>
      </div>
      <div class="form-group col-md-6" id="user-select"><label>Customer</label>
       <select class="form-control" name="user_id">
        @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->email }} — {{ $user->username }}</option>@endforeach
       </select>
      </div>
     </div>
     <div class="form-group"><label>Subject</label><input class="form-control" name="subject" maxlength="191" required placeholder="Important update from Nodexa"></div>
     <div class="form-group"><label>Message</label><textarea class="form-control" name="message" rows="12" required placeholder="Hello @{{name}},&#10;&#10;Write your message here..."></textarea>
      <p class="help-block">Variables: <code>@{{name}}</code> <code>@{{username}}</code> <code>@{{email}}</code> <code>@{{app_name}}</code></p>
     </div>
    </div>
    <div class="box-footer"><button class="btn btn-primary pull-right"><i class="fa fa-paper-plane"></i> Send email</button></div>
   </form>
  </div>
 </div>
 <div class="col-md-4">
  <div class="box">
   <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-envelope"></i> Mail system</h3></div>
   <div class="box-body">
    <p><strong>Nodexa Mail Center</strong> uses the SMTP configuration already connected to the panel.</p>
    <p class="text-muted">Use Mail Settings to change SMTP server, sender address or send a test email.</p>
    <a href="{{ route('admin.settings.mail') }}" class="btn btn-default btn-block"><i class="fa fa-cog"></i> SMTP & Mail Settings</a>
   </div>
  </div>
 </div>
</div>
@endsection

@section('footer-scripts')
@parent
<script>
$(function(){
 function audience(){ $('#user-select').toggle($('#audience').val()==='single'); }
 $('#audience').on('change', audience); audience();
});
</script>
@endsection
