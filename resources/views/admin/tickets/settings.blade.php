@extends('layouts.admin')
@section('title', 'Support Settings')
@section('content-header')<h1>Support settings <small>Departments, access and automation</small></h1>@endsection
@section('content')
<a class="btn btn-default" href="{{ route('admin.tickets') }}" style="margin-bottom:16px">← Ticket queue</a>
<div class="row"><div class="col-md-8">
    <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Support desk</h3></div>
        <form method="POST" action="{{ route('admin.tickets.settings.save') }}">@csrf<div class="box-body">
            <label>Discord webhook</label><input class="form-control" type="url" name="discord_webhook" value="{{ $settings['discord_webhook'] }}"><p class="help-block">Optional notifications for new tickets, replies and escalations.</p>
            <div class="checkbox"><label><input type="checkbox" name="auto_assign" value="1" @checked($settings['auto_assign'] === '1')> Automatically assign to the least busy staff member in the department</label></div>
            <div class="checkbox"><label><input type="checkbox" name="allow_reopen" value="1" @checked($settings['allow_reopen'] === '1')> Allow customers to reopen closed tickets, including by email reply</label></div>
            <h4>SLA response targets</h4><div class="row">@foreach(['urgent', 'high', 'normal', 'low'] as $priority)<div class="col-sm-6"><label>{{ ucfirst($priority) }} (minutes)</label><input class="form-control" type="number" min="15" max="10080" name="sla_{{ $priority }}" value="{{ $settings['sla_'.$priority] }}" required></div>@endforeach</div>
            <p class="help-block">SLA runs while a ticket needs a staff reply. Notes and assignment changes do not reset the timer. New targets apply on the next customer reply or priority change.</p>
        </div><div class="box-footer"><button class="btn btn-primary">Save support settings</button></div></form>
    </div>
    <h3>Departments</h3><p class="text-muted">Staff need both their existing Tickets role permission and department membership. Root administrators retain access to all departments.</p>
    @foreach($departments->concat([null]) as $department)
    <div class="box"><div class="box-header with-border"><h3 class="box-title">{{ $department?->name ?? 'Add department' }}</h3></div>
        <form method="POST" action="{{ route('admin.tickets.departments.save') }}">@csrf<div class="box-body">
            <div class="row"><div class="col-sm-4"><label>Key</label><input class="form-control" name="key" maxlength="40" pattern="[a-z][a-z0-9_]{0,39}" value="{{ $department?->key }}" @readonly($department) required></div><div class="col-sm-8"><label>Name</label><input class="form-control" name="name" maxlength="100" value="{{ $department?->name }}" required></div></div><br>
            <label>Department email (optional)</label><input type="email" class="form-control" name="email" value="{{ $department?->email }}"><p class="help-block">Display / contact address. Ticket replies use the configured shared support mailbox.</p>
            <label>Assigned staff</label><select class="form-control" name="staff[]" multiple size="5">@foreach($staff as $u)<option value="{{ $u->id }}" @selected($department?->staff->contains('id', $u->id))>{{ $u->username }}{{ $u->root_admin ? ' (root — all departments)' : '' }}</option>@endforeach</select>
            <div class="checkbox"><label><input type="checkbox" name="enabled" value="1" @checked(!$department || $department->enabled)> Accept new tickets</label></div>
            <div class="checkbox"><label><input type="checkbox" name="hidden" value="1" @checked($department?->hidden)> Hide from new ticket forms (existing tickets remain accessible)</label></div>
            <div class="checkbox"><label><input type="checkbox" name="prevent_client_close" value="1" @checked($department?->prevent_client_close)> Only staff can close tickets</label></div>
            <div class="row"><div class="col-sm-4"><label>Auto-close after (hours)</label><input class="form-control" type="number" name="auto_close_hours" min="0" max="8760" value="{{ $department?->auto_close_hours ?? 0 }}" required></div><div class="col-sm-4"><label>Escalate after SLA overdue (minutes)</label><input class="form-control" type="number" name="escalation_minutes" min="0" max="43200" value="{{ $department?->escalation_minutes ?? 0 }}" required></div><div class="col-sm-4"><label>Escalated priority</label><select class="form-control" name="escalation_priority">@foreach(['low', 'normal', 'high', 'urgent'] as $p)<option value="{{ $p }}" @selected(($department?->escalation_priority ?? 'high') === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div></div>
            <p class="help-block">0 disables the rule. Auto-close applies only after a staff reply while waiting for the customer. Escalation never lowers a priority and runs once per response cycle.</p>
        </div><div class="box-footer"><button class="btn btn-primary">{{ $department ? 'Save department' : 'Create department' }}</button></div></form>
    </div>
    @endforeach
</div><div class="col-md-4">
    <div class="box box-info"><div class="box-header with-border"><h3 class="box-title">Email replies</h3></div><div class="box-body">
        <p>Status: <strong>{{ config('support.mail.enabled') ? 'Enabled' : 'Not configured' }}</strong></p>
        <p>Customers can reply to ticket emails after connecting a TLS IMAP mailbox that accepts plus addressing. Each ticket has a private reply address.</p>
        <p>Configure SUPPORT_MAIL_ENABLED, SUPPORT_MAIL_ADDRESS and SUPPORT_IMAP_* in the server environment. Passwords are never displayed here.</p>
        <a class="btn btn-default btn-block" href="{{ route('admin.settings.mail') }}">Outgoing mail settings</a>
        <hr><h4>Scheduled processing</h4><p>Mail imports run every minute. Escalation and inactive closure run every five minutes using the existing Laravel scheduler.</p>
    </div></div>
    <h3>Predefined replies</h3><p class="text-muted">Reusable text; staff review it before sending.</p>
    @foreach($cannedReplies->concat([null]) as $reply)
    <div class="box"><form method="POST" action="{{ route('admin.tickets.canned.save') }}">@csrf<div class="box-body"><input type="hidden" name="id" value="{{ $reply?->id }}"><label>Title</label><input class="form-control" name="title" value="{{ $reply?->title }}" maxlength="160" required><br><label>Department</label><select class="form-control" name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected($reply?->department_id === $department->id)>{{ $department->name }}</option>@endforeach</select><br><label>Reply text</label><textarea class="form-control" rows="6" name="body" minlength="2" maxlength="20000" required>{{ $reply?->body }}</textarea></div><div class="box-footer"><button class="btn btn-primary">{{ $reply ? 'Save reply' : 'Add reply' }}</button></div></form>
        @if($reply)<form style="padding:0 10px 10px" method="POST" action="{{ route('admin.tickets.canned.delete', $reply->id) }}" onsubmit="return confirm('Delete this predefined reply?')">@csrf @method('DELETE')<button class="btn btn-default btn-sm">Delete</button></form>@endif
    </div>
    @endforeach
</div></div>
<div class="box"><div class="box-header with-border"><h3 class="box-title">Recent mail imports</h3></div><div class="box-body table-responsive"><table class="table"><thead><tr><th>Time</th><th>Sender</th><th>Status</th><th>Result</th></tr></thead><tbody>@forelse($imports as $mail)<tr><td>{{ $mail->created_at }}</td><td>{{ $mail->sender }}</td><td>{{ $mail->status }}</td><td>{{ $mail->reason ?? ($mail->ticket_id ? 'Ticket #' . $mail->ticket_id : '—') }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No mail processed yet.</td></tr>@endforelse</tbody></table></div></div>
@endsection
