<?php

namespace Pterodactyl\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Models\SupportDepartment;
use Pterodactyl\Models\SupportSetting;
use Pterodactyl\Models\SupportTicket;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Nodexa\NodexaEventService;

class TicketWorkflow
{
    public const STATUSES = ['open', 'customer_reply', 'waiting_customer', 'answered', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const FILE_RULES = ['attachments' => 'nullable|array|max:10', 'attachments.*' => 'file|max:10240|mimes:png,jpg,jpeg,gif,webp,pdf,txt,log,zip'];

    public function __construct(private SupportAccess $access, private TicketNotificationService $notifications, private NodexaEventService $events)
    {
    }

    public function due(string $priority)
    {
        $defaults = ['urgent' => 60, 'high' => 240, 'normal' => 720, 'low' => 1440];
        return now()->addMinutes((int) SupportSetting::value('sla_' . $priority, $defaults[$priority] ?? 720));
    }

    private function event(SupportTicket $ticket, ?User $actor, string $event, string $details): void
    {
        $ticket->events()->create(['user_id' => $actor?->id, 'event' => $event, 'details' => $details]);
    }

    private function message(SupportTicket $ticket, ?User $actor, string $body, bool $staff, bool $internal, array $files)
    {
        $message = $ticket->messages()->create(['user_id' => $actor?->id, 'message' => $body, 'staff' => $staff, 'internal' => $internal]);
        foreach ($files as $file) {
            $path = $file->store('support-tickets/' . $ticket->id, 'local');
            if (!$path) {
                throw new \RuntimeException('Could not store attachment.');
            }
            $ticket->attachments()->create(['message_id' => $message->id, 'user_id' => $actor?->id, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
        }
        return $message;
    }

    public function create(User $customer, array $data, array $files = []): SupportTicket
    {
        $ticket = DB::transaction(function () use ($customer, $data, $files) {
            $department = SupportDepartment::where('key', $data['department'])->where('enabled', true)->where('hidden', false)->firstOrFail();
            $ticket = SupportTicket::create([
                'ticket_number' => 'NX-' . strtoupper(Str::random(8)), 'reply_token' => Str::random(40),
                'user_id' => $customer->id, 'assigned_to' => SupportSetting::value('auto_assign', '1') === '1' ? $this->access->assignee($department) : null,
                'server_id' => $data['server_id'] ?? null, 'subject' => $data['subject'], 'department' => $department->key,
                'priority' => $data['priority'] ?? 'normal', 'status' => 'open', 'last_reply_at' => now(), 'last_customer_reply_at' => now(), 'due_at' => $this->due($data['priority'] ?? 'normal'),
            ]);
            $this->message($ticket, $customer, $data['message'], false, false, $files);
            $this->event($ticket, $customer, 'created', 'Ticket created');
            return $ticket;
        });
        $this->notifications->created($ticket);
        $this->notifyStaff($ticket, 'New support ticket');
        $this->events->emit('ticket.created', ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'subject' => $ticket->subject], $customer->id);
        return $ticket;
    }

    public function reply(SupportTicket $ticket, User $actor, string $body, bool $staff = false, bool $internal = false, string $status = 'answered', array $files = [], bool $reopen = false): void
    {
        DB::transaction(function () use ($ticket, $actor, $body, $staff, $internal, $status, $files, $reopen) {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($staff) {
                $this->access->authorize($actor, $ticket, true);
            } else {
                abort_unless($ticket->user_id === $actor->id, 403);
            }
            abort_if($ticket->merged_into_id || ($ticket->status === 'closed' && !$reopen), 422, 'Reopen this ticket before replying.');
            abort_unless(in_array($status, self::STATUSES, true), 422);
            $this->message($ticket, $actor, $body, $staff, $internal, $files);
            if (!$internal) {
                $next = $staff ? $status : 'customer_reply';
                $ticket->fill(['status' => $next, 'last_reply_at' => now(), 'closed_at' => $next === 'closed' ? now() : null, 'resolved_at' => in_array($next, ['answered', 'closed']) ? now() : null, 'due_at' => in_array($next, ['open', 'customer_reply']) ? $this->due($ticket->priority) : null, 'escalated_at' => null]);
                if ($staff) {
                    $ticket->first_response_at ??= now();
                    $ticket->last_staff_reply_at = now();
                } else {
                    $ticket->last_customer_reply_at = now();
                }
                $ticket->save();
            }
            $this->event($ticket, $actor, $internal ? 'internal_note' : ($staff ? 'staff_reply' : 'customer_reply'), $internal ? 'Internal note added' : 'Reply added');
            DB::afterCommit(function () use ($ticket, $staff, $internal) {
                if ($internal) {
                    return;
                }
                if ($staff) {
                    $this->notifications->staffReply($ticket);
                    $this->events->notify($ticket->user_id, 'Reply to ' . $ticket->ticket_number, $ticket->subject, 'ticket', '/client/tickets/' . $ticket->id);
                    if ($ticket->status === 'closed') {
                        $this->notifications->closed($ticket);
                    }
                } else {
                    $this->notifications->customerReply($ticket);
                    $this->notifyStaff($ticket, 'Customer reply');
                }
                $this->events->emit('ticket.reply', ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'from' => $staff ? 'staff' : 'customer'], $ticket->user_id);
            });
        });
    }

    public function changeStatus(SupportTicket $ticket, ?User $actor, string $status, string $reason, bool $customer = false): void
    {
        DB::transaction(function () use ($ticket, $actor, $status, $reason, $customer) {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_if($ticket->merged_into_id, 422);
            if ($customer) {
                abort_unless($actor && $ticket->user_id === $actor->id, 403);
                abort_if($status === 'closed' && $ticket->departmentConfig?->prevent_client_close, 403);
                abort_if($status === 'open' && ($ticket->status !== 'closed' || SupportSetting::value('allow_reopen', '1') !== '1'), 422);
                abort_unless(in_array($status, ['open', 'closed'], true), 422);
            } elseif ($actor) {
                $this->access->authorize($actor, $ticket, true);
            }
            abort_unless(in_array($status, self::STATUSES, true), 422);
            if ($ticket->status === $status) {
                return;
            }
            $ticket->update(['status' => $status, 'closed_at' => $status === 'closed' ? now() : null, 'resolved_at' => in_array($status, ['closed', 'answered']) ? now() : null, 'due_at' => in_array($status, ['open', 'customer_reply']) ? $this->due($ticket->priority) : null, 'escalated_at' => null]);
            $this->event($ticket, $actor, $status === 'closed' ? 'closed' : 'reopened', $reason);
            if ($status === 'closed') {
                DB::afterCommit(fn () => $this->notifications->closed($ticket));
            }
        });
    }

    public function merge(SupportTicket $source, SupportTicket $target, User $actor): void
    {
        DB::transaction(function () use ($source, $target, $actor) {
            $locked = SupportTicket::whereIn('id', [$source->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked[$source->id];
            $target = $locked[$target->id];
            $this->access->authorize($actor, $source, true);
            $this->access->authorize($actor, $target, true);
            if ($source->id === $target->id || $source->user_id !== $target->user_id || $source->merged_into_id || $target->merged_into_id || $target->status === 'closed') {
                throw ValidationException::withMessages(['target_id' => 'Choose an open, unmerged ticket belonging to the same customer.']);
            }
            $source->messages()->update(['ticket_id' => $target->id]);
            $source->attachments()->update(['ticket_id' => $target->id]);
            $source->events()->update(['ticket_id' => $target->id]);
            $source->update(['merged_into_id' => $target->id, 'status' => 'closed', 'closed_at' => now(), 'due_at' => null]);
            $target->update(['status' => 'open', 'last_reply_at' => now(), 'due_at' => $this->due($target->priority), 'resolved_at' => null, 'escalated_at' => null]);
            $this->event($source, $actor, 'merged', 'Merged into ' . $target->ticket_number);
            $this->event($target, $actor, 'merged', $source->ticket_number . ' merged into this ticket');
        });
    }

    public function notifyStaff(SupportTicket $ticket, string $title): void
    {
        $department = $ticket->departmentConfig;
        if (!$department) {
            return;
        }
        $staff = $this->access->staff($department);
        if ($ticket->assigned_to) {
            $staff = $staff->where('id', $ticket->assigned_to);
        }
        foreach ($staff as $user) {
            $this->events->notify($user->id, $title . ' · ' . $ticket->ticket_number, $ticket->subject, 'ticket', '/admin/tickets/' . $ticket->id);
        }
    }
}
