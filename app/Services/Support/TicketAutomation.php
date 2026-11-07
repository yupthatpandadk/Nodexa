<?php

namespace Pterodactyl\Services\Support;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\SupportDepartment;
use Pterodactyl\Models\SupportTicket;

class TicketAutomation
{
    public function __construct(private TicketWorkflow $workflow, private TicketNotificationService $notifications)
    {
    }

    public function run(): array
    {
        $counts = ['closed' => 0, 'escalated' => 0];
        foreach (SupportDepartment::all() as $department) {
            SupportTicket::where('department', $department->key)->whereNull('merged_into_id')->where('status', '!=', 'closed')->chunkById(100, function ($tickets) use ($department, &$counts) {
                foreach ($tickets as $candidate) {
                    DB::transaction(function () use ($candidate, $department, &$counts) {
                        $ticket = SupportTicket::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                        if ($ticket->merged_into_id || $ticket->status === 'closed' || $ticket->department !== $department->key) {
                            return;
                        }
                        if ($department->auto_close_hours > 0 && in_array($ticket->status, ['waiting_customer', 'answered'], true)
                            && $ticket->last_staff_reply_at && $ticket->last_staff_reply_at->lte(now()->subHours($department->auto_close_hours))
                            && (!$ticket->last_customer_reply_at || $ticket->last_customer_reply_at->lte($ticket->last_staff_reply_at))) {
                            $this->workflow->changeStatus($ticket, null, 'closed', 'Automatically closed after ' . $department->auto_close_hours . ' hours without a customer reply');
                            $counts['closed']++;
                            return;
                        }
                        if ($department->escalation_minutes > 0 && in_array($ticket->status, ['open', 'customer_reply'], true)
                            && !$ticket->escalated_at && $ticket->due_at && $ticket->due_at->lte(now()->subMinutes($department->escalation_minutes))) {
                            $priority = array_search($department->escalation_priority, TicketWorkflow::PRIORITIES, true) > array_search($ticket->priority, TicketWorkflow::PRIORITIES, true) ? $department->escalation_priority : $ticket->priority;
                            $ticket->update(['priority' => $priority, 'escalated_at' => now()]);
                            $ticket->events()->create(['event' => 'escalated', 'details' => 'SLA exceeded; priority ' . $priority]);
                            DB::afterCommit(function () use ($ticket) {
                                $this->workflow->notifyStaff($ticket, 'Overdue ticket escalated');
                                $this->notifications->escalated($ticket);
                            });
                            $counts['escalated']++;
                        }
                    });
                }
            });
        }
        return $counts;
    }
}
