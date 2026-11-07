<?php

namespace Pterodactyl\Services\Support;

use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Models\SupportDepartment;
use Pterodactyl\Models\SupportTicket;
use Pterodactyl\Models\User;

class SupportAccess
{
    public function departments(User $user): Builder
    {
        return SupportDepartment::query()->when(!$user->root_admin, fn ($q) => $q->whereHas('staff', fn ($s) => $s->where('users.id', $user->id)));
    }

    public function tickets(User $user): Builder
    {
        return SupportTicket::query()->when(!$user->root_admin, fn ($q) => $q->whereIn('department', $this->departments($user)->select('key')));
    }

    public function authorize(User $user, SupportTicket $ticket, bool $manage = false): void
    {
        abort_unless($user->hasPermission('tickets.' . ($manage ? 'manage' : 'view')) && $this->tickets($user)->whereKey($ticket->id)->exists(), 403);
    }

    public function staff(SupportDepartment $department)
    {
        return User::query()->where(function ($q) use ($department) {
            $q->where('root_admin', true)->orWhereIn('id', $department->staff()->select('users.id'));
        })->orderBy('username')->get()->filter(fn (User $u) => $u->hasPermission('tickets.manage'))->values();
    }

    public function assignee(SupportDepartment $department): ?int
    {
        $ids = $this->staff($department)->pluck('id');
        return User::whereIn('id', $ids)->withCount(['assignedSupportTickets' => fn ($q) => $q->where('status', '!=', 'closed')])->orderBy('assigned_support_tickets_count')->orderBy('id')->value('id');
    }
}
