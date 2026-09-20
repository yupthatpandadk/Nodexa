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
     <div class="form-group">
      <label>Mail template</label>
      <select class="form-control" id="mail-template">
       @foreach($templates as $key => $template)
        <option value="{{ $key }}" data-subject="{{ $template['subject'] }}" data-message="{{ $template['message'] }}">{{ $template['name'] }}</option>
       @endforeach
      </select>
      <p class="help-block">Choose a template and customize it before sending.</p>
     </div>
     <div class="row" id="maintenance-window" style="display:none">
      <div class="form-group col-md-6">
       <label>Maintenance from</label>
       <input type="datetime-local" class="form-control" id="maintenance-from">
      </div>
      <div class="form-group col-md-6">
       <label>Maintenance to</label>
       <input type="datetime-local" class="form-control" id="maintenance-to">
      </div>
      <div class="col-md-12"><p class="help-block">The selected period is automatically inserted into the planned maintenance email.</p></div>
     </div>
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
     <div class="form-group"><label>Subject</label><input class="form-control" id="mail-subject" name="subject" maxlength="191" required placeholder="Important update from Nodexa"></div>
     <div class="form-group"><label>Message</label><textarea class="form-control" id="mail-message" name="message" rows="12" required placeholder="Hello @{{name}},&#10;&#10;Write your message here..."></textarea>
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
  <div class="box box-info">
   <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-flask"></i> Send test email</h3></div>
   <form method="POST" action="{{ route('admin.mail.test') }}" id="test-mail-form">
    {!! csrf_field() !!}
    <div class="box-body">
     <p class="text-muted">Send the current subject and message to one email address without contacting customers.</p>
     <div class="form-group"><label>Test recipient</label><input type="email" class="form-control" name="test_email" value="{{ auth()->user()->email }}" required></div>
     <input type="hidden" name="subject" id="test-subject">
     <input type="hidden" name="message" id="test-message">
    </div>
    <div class="box-footer"><button class="btn btn-info btn-block"><i class="fa fa-paper-plane-o"></i> Send test</button></div>
   </form>
  </div>
  </div>
 </div>
</div>

<div class="row">
 <div class="col-md-6">
  <div class="box box-success">
   <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-repeat"></i> Resend welcome email</h3></div>
   <div class="box-body">
    <p class="text-muted">Choose a customer to resend their Nodexa welcome email.</p>
    @foreach($users as $user)
     <form method="POST" action="{{ route('admin.mail.resend-welcome', $user->id) }}" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
      {!! csrf_field() !!}
      <span style="flex:1"><strong>{{ $user->username }}</strong><br><small>{{ $user->email }}</small></span>
      <button class="btn btn-default btn-sm"><i class="fa fa-repeat"></i> Resend</button>
     </form>
    @endforeach
   </div>
  </div>
 </div>
 <div class="col-md-6">
  <div class="box box-warning">
   <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-server"></i> Resend server email</h3></div>
   <div class="box-body">
    <p class="text-muted">Resend server details to the owner. Showing the 50 newest servers.</p>
    @forelse($recentServers as $server)
     <form method="POST" action="{{ route('admin.mail.resend-server', $server->id) }}" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
      {!! csrf_field() !!}
      <span style="flex:1"><strong>#{{ $server->id }} · {{ $server->name }}</strong><br><small>{{ $server->user ? $server->user->email : 'No owner' }}</small></span>
      <button class="btn btn-default btn-sm" @if(!$server->user) disabled @endif><i class="fa fa-repeat"></i> Resend</button>
     </form>
    @empty
     <p class="text-muted">No servers found.</p>
    @endforelse
   </div>
  </div>
 </div>
</div>
@endsection

@section('footer-scripts')
@parent
<script>
$(function(){
 var templateBaseMessage = '';

 function formatMaintenanceDate(value) {
   if (!value) return '';
   var date = new Date(value);
   if (isNaN(date.getTime())) return value;
   return new Intl.DateTimeFormat('da-DK', {
     day: '2-digit', month: '2-digit', year: 'numeric',
     hour: '2-digit', minute: '2-digit'
   }).format(date);
 }

 function updateMaintenanceMessage() {
   if ($('#mail-template').val() !== 'maintenance') return;
   var from = formatMaintenanceDate($('#maintenance-from').val());
   var to = formatMaintenanceDate($('#maintenance-to').val());
   var period = '';
   if (from && to) period = '\n\nMaintenance window: ' + from + ' - ' + to;
   else if (from) period = '\n\nMaintenance starts: ' + from;
   else if (to) period = '\n\nMaintenance is expected to end: ' + to;
   $('#mail-message').val(templateBaseMessage.replace('[[MAINTENANCE_WINDOW]]', period));
 }

 function audience(){ $('#user-select').toggle($('#audience').val()==='single'); }
 $('#audience').on('change', audience); audience();

 $('#mail-template').on('change', function(){
   var option = $(this).find(':selected');
   $('#mail-subject').val(option.data('subject') || '');
   templateBaseMessage = option.data('message') || '';
   $('#maintenance-window').toggle($(this).val() === 'maintenance');
   if ($(this).val() === 'maintenance') {
     updateMaintenanceMessage();
   } else {
     $('#mail-message').val(templateBaseMessage);
   }
 }).trigger('change');

 $('#maintenance-from, #maintenance-to').on('change input', updateMaintenanceMessage);

 $('#test-mail-form').on('submit', function(){
   $('#test-subject').val($('#mail-subject').val());
   $('#test-message').val($('#mail-message').val());
 });
});
</script>
@endsection
