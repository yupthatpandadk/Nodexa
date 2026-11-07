@extends('layouts.admin')
@section('title', 'Support Tickets')
@section('content-header')
<h1>Support Tickets <small>Customer support desk</small></h1>
@endsection
@section('content')
<div class="row">
    @foreach(['open' => 'Needs a reply', 'overdue' => 'Overdue SLA', 'waiting' => 'Waiting for customer', 'rating' => 'Customer rating / 5'] as $key => $label)
    <div class="col-sm-3"><div class="small-box bg-{{ $key === 'overdue' ? 'red' : 'blue' }}"><div class="inner"><h3>{{ $key === 'rating' ? ($stats[$key] ? number_format($stats[$key], 1) : '—') : $stats[$key] }}</h3><p>{{ $label }}</p></div></div></div>
    @endforeach
</div>
<div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Ticket queue</h3>
        @if(auth()->user()->root_admin)<a href="{{ route('admin.tickets.settings') }}" class="btn btn-default btn-sm pull-right"><i class="fa fa-cog"></i> Support settings</a>@endif
    </div>
    <div class="box-body">
        <form class="form-inline" method="GET">
            <input class="form-control input-sm" type="search" name="q" value="{{ request('q') }}" placeholder="Ticket, subject or customer" aria-label="Search tickets">
            <select class="form-control input-sm" name="status" aria-label="Status"><option value="">All statuses</option>@foreach(\Pterodactyl\Services\Support\TicketWorkflow::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach</select>
            <select class="form-control input-sm" name="department" aria-label="Department"><option value="">All departments</option>@foreach($departments as $d)<option value="{{ $d->key }}" @selected(request('department') === $d->key)>{{ $d->name }}</option>@endforeach</select>
            <select class="form-control input-sm" name="assigned_to" aria-label="Assigned staff"><option value="">All staff</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected((string)request('assigned_to') === (string)$u->id)>{{ $u->username }}</option>@endforeach</select>
            <select class="form-control input-sm" name="priority" aria-label="Priority"><option value="">All priorities</option>@foreach(\Pterodactyl\Services\Support\TicketWorkflow::PRIORITIES as $p)<option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>@endforeach</select>
            <label style="margin:8px"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> Overdue only</label>
            <button class="btn btn-primary btn-sm">Search</button> <a class="btn btn-default btn-sm" href="{{ route('admin.tickets') }}">Reset</a>
        </form>
    </div>
    <div class="box-body table-responsive no-padding">
        <table class="table table-hover"><thead><tr><th>Ticket</th><th>Customer</th><th>Department</th><th>Priority</th><th>Status</th><th>Assigned</th><th>SLA</th><th>Rating</th><th>Last reply</th></tr></thead><tbody>
        @forelse($tickets as $t)
            <tr><td><a href="{{ route('admin.tickets.show', $t) }}"><strong>{{ $t->ticket_number }}</strong><br>{{ $t->subject }}</a></td><td>{{ $t->user->email ?? 'Deleted user' }}</td><td>{{ $t->department }}</td><td>{{ ucfirst($t->priority) }} @if($t->escalated_at)<span class="label label-danger">Escalated</span>@endif</td><td>{{ ucfirst(str_replace('_', ' ', $t->status)) }} @if($t->merged_into_id)<span class="label label-default">Merged</span>@endif</td><td>{{ $t->assignee->username ?? 'Unassigned' }}</td><td>@if($t->due_at && in_array($t->status, ['open', 'customer_reply']))<span class="{{ $t->due_at->isPast() ? 'text-red' : '' }}">{{ $t->due_at->isPast() ? 'OVERDUE' : $t->due_at->diffForHumans() }}</span>@else — @endif</td><td>{{ $t->rating ? $t->rating . '/5' : '—' }}</td><td>{{ $t->last_reply_at?->diffForHumans() ?? '—' }}</td></tr>
        @empty<tr><td colspan="9" class="text-center text-muted" style="padding:35px">No tickets match your filters.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="box-footer">{{ $tickets->links() }}</div>
</div>
@endsection
