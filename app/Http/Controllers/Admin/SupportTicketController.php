<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\SupportCannedReply;
use Pterodactyl\Models\SupportDepartment;
use Pterodactyl\Models\SupportSetting;
use Pterodactyl\Models\SupportTicket;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Support\SupportAccess;
use Pterodactyl\Services\Support\TicketNotificationService;
use Pterodactyl\Services\Support\TicketWorkflow;

class SupportTicketController extends Controller
{
    public function __construct(private SupportAccess $access, private TicketWorkflow $workflow, private TicketNotificationService $notifications)
    {
    }

    public function index(Request $r)
    {
        $q = $this->access->tickets($r->user())->with(['user', 'server', 'assignee'])->withCount('messages');
        foreach (['status', 'priority', 'department', 'assigned_to'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->input($f));
            }
        }
        if ($r->filled('q')) {
            $term = '%' . mb_substr($r->string('q')->toString(), 0, 160) . '%';
            $q->where(fn ($q) => $q->where('ticket_number', 'like', $term)->orWhere('subject', 'like', $term)->orWhereHas('user', fn ($q) => $q->where('email', 'like', $term)));
        }
        if ($r->boolean('overdue')) {
            $q->whereIn('status', ['open', 'customer_reply'])->where('due_at', '<', now());
        }
        $base = $this->access->tickets($r->user())->whereNull('merged_into_id');
        return view('admin.tickets.index', [
            'tickets' => $q->latest('last_reply_at')->paginate(30)->withQueryString(),
            'departments' => $this->access->departments($r->user())->orderBy('name')->get(),
            'staff' => User::where('root_admin', true)->orWhereHas('roles')->orderBy('username')->get()->filter(fn ($u) => $u->hasPermission('tickets.view')),
            'stats' => ['open' => (clone $base)->whereIn('status', ['open', 'customer_reply'])->count(), 'overdue' => (clone $base)->whereIn('status', ['open', 'customer_reply'])->where('due_at', '<', now())->count(), 'waiting' => (clone $base)->whereIn('status', ['waiting_customer', 'answered'])->count(), 'rating' => (clone $base)->avg('rating')],
        ]);
    }

    public function show(Request $r, SupportTicket $ticket)
    {
        $this->access->authorize($r->user(), $ticket);
        if ($ticket->merged_into_id) {
            $target = SupportTicket::findOrFail($ticket->merged_into_id);
            $this->access->authorize($r->user(), $target);
            return redirect()->route('admin.tickets.show', $target);
        }
        $ticket->load(['user', 'server', 'assignee', 'messages' => fn ($q) => $q->orderBy('created_at')->orderBy('id')->with(['user', 'attachments']), 'events.user', 'departmentConfig']);
        $department = $ticket->departmentConfig;
        return view('admin.tickets.show', [
            'ticket' => $ticket, 'staff' => $department ? $this->access->staff($department) : collect(),
            'departments' => $this->access->departments($r->user())->orderBy('name')->get(),
            'cannedReplies' => SupportCannedReply::where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $department?->id))->orderBy('title')->get(),
            'mergeTargets' => $this->access->tickets($r->user())->where('user_id', $ticket->user_id)->where('id', '!=', $ticket->id)->whereNull('merged_into_id')->where('status', '!=', 'closed')->latest()->limit(100)->get(),
        ]);
    }

    public function reply(Request $r, SupportTicket $ticket)
    {
        $this->access->authorize($r->user(), $ticket, true);
        $d = $r->validate(['message' => 'required|string|min:2|max:20000', 'status' => ['required', Rule::in(TicketWorkflow::STATUSES)], 'internal' => 'nullable|boolean', ...TicketWorkflow::FILE_RULES]);
        $this->workflow->reply($ticket, $r->user(), $d['message'], true, $r->boolean('internal'), $d['status'], $r->file('attachments', []));
        return back()->with('success', 'Reply saved.');
    }

    public function update(Request $r, SupportTicket $ticket)
    {
        $this->access->authorize($r->user(), $ticket, true);
        $d = $r->validate(['status' => ['required', Rule::in(TicketWorkflow::STATUSES)], 'priority' => ['required', Rule::in(TicketWorkflow::PRIORITIES)], 'department' => ['required', Rule::exists('nodexa_support_departments', 'key')], 'assigned_to' => 'nullable|integer', 'tags' => 'nullable|string|max:500']);
        $d['assigned_to'] = $d['assigned_to'] ?? null;
        DB::transaction(function () use ($r, $ticket, $d) {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($r->user(), $ticket, true);
            abort_if($ticket->merged_into_id, 422);
            $department = $this->access->departments($r->user())->where('key', $d['department'])->firstOrFail();
            $assignee = $d['assigned_to'] ?? null;
            abort_if($assignee && !$this->access->staff($department)->contains('id', $assignee), 422, 'Staff member must have manage permission and access to the selected department.');
            $before = $ticket->only(['status', 'priority', 'department', 'assigned_to', 'tags']);
            $statusChanged = $ticket->status !== $d['status'];
            $ticket->fill($d);
            if ($statusChanged || $ticket->isDirty('priority')) {
                $ticket->due_at = in_array($d['status'], ['open', 'customer_reply']) ? $this->workflow->due($d['priority']) : null;
                $ticket->escalated_at = null;
            }
            if ($statusChanged) {
                $ticket->closed_at = $d['status'] === 'closed' ? now() : null;
                $ticket->resolved_at = in_array($d['status'], ['closed', 'answered']) ? now() : null;
            }
            $ticket->save();
            $ticket->events()->create(['user_id' => $r->user()->id, 'event' => 'updated', 'details' => json_encode(['before' => $before, 'after' => $d])]);
            if ($statusChanged && $ticket->status === 'closed') {
                DB::afterCommit(fn () => $this->notifications->closed($ticket));
            }
        });
        return back()->with('success', 'Ticket updated.');
    }

    public function merge(Request $r, SupportTicket $ticket)
    {
        $this->access->authorize($r->user(), $ticket, true);
        $d = $r->validate(['target_id' => 'required|integer|different:ticket']);
        $target = $this->access->tickets($r->user())->findOrFail($d['target_id']);
        $this->workflow->merge($ticket, $target, $r->user());
        return redirect()->route('admin.tickets.show', $target)->with('success', 'Tickets merged.');
    }

    public function settings(Request $r)
    {
        abort_unless($r->user()->root_admin, 403);
        $defaults = ['discord_webhook' => '', 'auto_assign' => '1', 'allow_reopen' => '1', 'sla_urgent' => '60', 'sla_high' => '240', 'sla_normal' => '720', 'sla_low' => '1440'];
        $settings = [];
        foreach ($defaults as $k => $v) {
            $settings[$k] = SupportSetting::value($k, $v);
        }
        return view('admin.tickets.settings', ['settings' => $settings, 'departments' => SupportDepartment::with('staff')->orderBy('name')->get(), 'staff' => User::where('root_admin', true)->orWhereHas('roles')->orderBy('username')->get()->filter(fn ($u) => $u->hasPermission('tickets.view') || $u->hasPermission('tickets.manage')), 'cannedReplies' => SupportCannedReply::orderBy('title')->get(), 'imports' => DB::table('nodexa_support_mail_imports')->latest('id')->limit(30)->get()]);
    }

    public function saveSettings(Request $r)
    {
        abort_unless($r->user()->root_admin, 403);
        $d = $r->validate(['discord_webhook' => 'nullable|url:https|max:1000', 'auto_assign' => 'nullable|boolean', 'allow_reopen' => 'nullable|boolean', 'sla_urgent' => 'required|integer|min:15|max:10080', 'sla_high' => 'required|integer|min:15|max:10080', 'sla_normal' => 'required|integer|min:15|max:10080', 'sla_low' => 'required|integer|min:15|max:10080']);
        foreach ($d as $k => $v) {
            SupportSetting::put($k, (string) $v);
        }
        foreach (['auto_assign', 'allow_reopen'] as $key) {
            SupportSetting::put($key, $r->boolean($key) ? '1' : '0');
        }
        return back()->with('success', 'Support settings saved.');
    }

    public function saveDepartment(Request $r)
    {
        abort_unless($r->user()->root_admin, 403);
        $d = $r->validate(['key' => 'required|regex:/^[a-z][a-z0-9_]{0,39}$/', 'name' => 'required|string|max:100', 'email' => ['nullable', 'email', 'max:255', Rule::unique('nodexa_support_departments', 'email')->ignore($r->input('key'), 'key')], 'staff' => 'nullable|array', 'staff.*' => 'integer|exists:users,id', 'auto_close_hours' => 'required|integer|min:0|max:8760', 'escalation_minutes' => 'required|integer|min:0|max:43200', 'escalation_priority' => ['required', Rule::in(TicketWorkflow::PRIORITIES)]]);
        foreach (['enabled', 'hidden', 'prevent_client_close'] as $key) {
            $d[$key] = $r->boolean($key);
        }
        $ids = $d['staff'] ?? [];
        unset($d['staff']);
        foreach (User::whereIn('id', $ids)->get() as $staff) {
            abort_unless($staff->hasPermission('tickets.view') || $staff->hasPermission('tickets.manage'), 422, 'Department members need a support role.');
        }
        DB::transaction(function () use ($d, $ids) {
            $department = SupportDepartment::updateOrCreate(['key' => $d['key']], $d);
            $department->staff()->sync($ids);
            // Remove assignments that no longer have access or reply permission.
            SupportTicket::where('department', $department->key)->whereNotIn('assigned_to', $this->access->staff($department)->pluck('id'))->update(['assigned_to' => null]);
        });
        return back()->with('success', 'Department saved.');
    }

    public function saveCannedReply(Request $r)
    {
        abort_unless($r->user()->root_admin, 403);
        $d = $r->validate(['id' => 'nullable|integer|exists:nodexa_support_canned_replies,id', 'department_id' => 'nullable|exists:nodexa_support_departments,id', 'title' => 'required|string|max:160', 'body' => 'required|string|min:2|max:20000']);
        $reply = empty($d['id']) ? new SupportCannedReply() : SupportCannedReply::findOrFail($d['id']);
        $reply->fill(collect($d)->except('id')->all())->save();
        return back()->with('success', 'Predefined reply saved.');
    }

    public function deleteCannedReply(Request $r, int $reply)
    {
        abort_unless($r->user()->root_admin, 403);
        SupportCannedReply::findOrFail($reply)->delete();
        return back()->with('success', 'Predefined reply deleted.');
    }

    public function attachment(Request $r, SupportTicket $ticket, int $attachment)
    {
        $this->access->authorize($r->user(), $ticket);
        $a = $ticket->attachments()->findOrFail($attachment);
        return Storage::disk($a->disk)->download($a->path, $a->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
