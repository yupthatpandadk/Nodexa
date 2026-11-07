@extends('layouts.admin')
@section('title', $ticket->ticket_number)
@section('content-header')
<h1>{{ $ticket->ticket_number }} <small>{{ $ticket->subject }}</small></h1>
@endsection
@section('content')
<a class="btn btn-default" href="{{ route('admin.tickets') }}" style="margin-bottom:16px">← Ticket queue</a>
<div class="row"><div class="col-md-8">
    <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Conversation</h3></div><div class="box-body">
    @foreach($ticket->messages as $m)
        <article style="padding:16px;margin-bottom:14px;border:1px solid #ddd;border-left:4px solid {{ $m->internal ? '#d7a12a' : ($m->staff ? '#3c8dbc' : '#aaa') }};border-radius:4px">
            <strong>{{ $m->staff ? ($m->user->name ?? 'Nodexa Support') : ($m->user->name ?? 'Customer') }}</strong>
            <small class="text-muted pull-right">{{ $m->created_at->format('d/m/Y H:i') }}</small>
            @if($m->internal)<span class="label label-warning">INTERNAL NOTE</span>@endif
            <div style="white-space:pre-wrap;overflow-wrap:anywhere;margin-top:10px">{{ $m->message }}</div>
            @foreach($m->attachments as $a)<p style="margin-top:8px"><a href="{{ route('admin.tickets.attachment', [$ticket, $a->id]) }}"><i class="fa fa-paperclip"></i> {{ $a->original_name }}</a></p>@endforeach
            @if($m->rating)<p class="text-muted">Customer rating: {{ $m->rating }}/5</p>@endif
        </article>
    @endforeach
    </div></div>
    @if(auth()->user()->hasPermission('tickets.manage') && $ticket->status !== 'closed')
    <div class="box box-success"><div class="box-header with-border"><h3 class="box-title">Reply to customer</h3></div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.tickets.reply', $ticket) }}">@csrf<div class="box-body">
            <label for="canned-reply">Predefined reply</label><select id="canned-reply" class="form-control"><option value="">Select a reply…</option>@foreach($cannedReplies as $reply)<option value="{{ $reply->id }}">{{ $reply->title }}</option>@endforeach</select>
            <p class="help-block">Inserts text into the editor. Review it before sending.</p>
            <label for="ticket-message">Message</label><textarea id="ticket-message" class="form-control" rows="8" name="message" minlength="2" maxlength="20000" required>{{ old('message') }}</textarea>
            <div class="checkbox"><label><input type="checkbox" name="internal" value="1"> Internal note — visible to staff only</label></div>
            <label for="ticket-files">Attachments</label><input id="ticket-files" type="file" name="attachments[]" multiple><p class="help-block">Up to 10 files, 10 MB per file.</p>
            <label for="reply-status">Status after reply</label><select id="reply-status" class="form-control" name="status"><option value="answered">Answered</option><option value="waiting_customer">Waiting for customer</option><option value="open">Open</option><option value="closed">Close after reply</option></select>
        </div><div class="box-footer"><button class="btn btn-success">Send reply / save note</button></div></form>
    </div>
    @elseif($ticket->status === 'closed')<div class="callout callout-info">This ticket is closed. Change its status to Open to continue the conversation.</div>@endif
    @if($ticket->rating)<div class="box"><div class="box-header"><h3 class="box-title">Customer feedback · {{ $ticket->rating }}/5</h3></div><div class="box-body" style="white-space:pre-wrap">{{ $ticket->feedback ?: 'No written feedback.' }}</div></div>@endif
    <div class="box"><div class="box-header"><h3 class="box-title">Activity log</h3></div><div class="box-body">
        @foreach($ticket->events->sortByDesc('created_at')->take(100) as $event)<details style="padding:8px 0;border-bottom:1px solid #eee"><summary>{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->event }} · {{ $event->user->username ?? 'System' }}</summary><pre style="white-space:pre-wrap">{{ $event->details }}</pre></details>@endforeach
    </div></div>
</div><div class="col-md-4">
    <div class="box"><div class="box-header with-border"><h3 class="box-title">Ticket details</h3></div>
        <div class="box-body"><strong>{{ $ticket->user->name ?? 'Customer' }}</strong><p>{{ $ticket->user->email ?? '' }}</p>@if($ticket->server)<p>Server: {{ $ticket->server->name }}</p>@endif
        @if($ticket->due_at)<p class="{{ $ticket->due_at->isPast() ? 'text-red' : '' }}">SLA target: {{ $ticket->due_at->format('d/m/Y H:i') }}</p>@endif
        @if($ticket->escalated_at)<p class="text-red">Escalated {{ $ticket->escalated_at->diffForHumans() }}</p>@endif
        </div>
        @if(auth()->user()->hasPermission('tickets.manage'))
        <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}">@csrf @method('PATCH')<div class="box-body">
            <label>Status</label><select class="form-control" name="status">@foreach(\Pterodactyl\Services\Support\TicketWorkflow::STATUSES as $s)<option value="{{ $s }}" @selected($ticket->status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach</select><br>
            <label>Priority</label><select class="form-control" name="priority">@foreach(\Pterodactyl\Services\Support\TicketWorkflow::PRIORITIES as $p)<option value="{{ $p }}" @selected($ticket->priority === $p)>{{ ucfirst($p) }}</option>@endforeach</select><br>
            <label>Department</label><select class="form-control" name="department" id="ticket-department">@foreach($departments as $d)<option value="{{ $d->key }}" @selected($ticket->department === $d->key)>{{ $d->name }}{{ $d->enabled ? '' : ' (disabled)' }}</option>@endforeach</select><p class="help-block">When transferring, leave the assignee empty and save before selecting staff in the new department.</p>
            <label>Assigned staff</label><select class="form-control" name="assigned_to" id="ticket-assignee"><option value="">Unassigned</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected($ticket->assigned_to === $u->id)>{{ $u->username }}</option>@endforeach</select><br>
            <label>Tags</label><input class="form-control" name="tags" value="{{ $ticket->tags }}" maxlength="500">
        </div><div class="box-footer"><button class="btn btn-primary btn-block">Update ticket</button></div></form>
        @endif
    </div>
    @if(auth()->user()->hasPermission('tickets.manage') && $mergeTargets->isNotEmpty())
    <div class="box box-warning"><div class="box-header with-border"><h3 class="box-title">Merge ticket</h3></div><form method="POST" action="{{ route('admin.tickets.merge', $ticket) }}" onsubmit="return confirm('Move this conversation and its attachments into the selected ticket?')">@csrf<div class="box-body"><p>Combine this ticket with another open ticket from the same customer. Old links and mail replies follow the merged conversation.</p><select class="form-control" name="target_id" required><option value="">Choose destination…</option>@foreach($mergeTargets as $target)<option value="{{ $target->id }}">{{ $target->ticket_number }} · {{ $target->subject }}</option>@endforeach</select></div><div class="box-footer"><button class="btn btn-warning btn-block">Merge into selected ticket</button></div></form></div>
    @endif
</div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const replies = @json($cannedReplies->mapWithKeys(fn($reply) => [$reply->id => $reply->body]));
    const picker = document.getElementById('canned-reply');
    if (picker) picker.addEventListener('change', function () {
        const editor = document.getElementById('ticket-message');
        if (!replies[this.value]) return;
        editor.value += (editor.value ? '\n\n' : '') + replies[this.value]; editor.focus(); this.value = '';
    });
    const department = document.getElementById('ticket-department');
    if (department) department.addEventListener('change', function () { document.getElementById('ticket-assignee').value = ''; });
});
</script>
@endsection
