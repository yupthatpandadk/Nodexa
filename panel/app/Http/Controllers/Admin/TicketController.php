<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $query = DB::table('nodexa_tickets as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->leftJoin('users as a', 'a.id', '=', 't.assigned_to')
            ->select('t.*', 'u.username', 'u.email', 'a.username as assigned_username');

        if ($request->filled('status') && in_array($request->query('status'), ['open', 'answered', 'customer_reply', 'closed'], true)) {
            $query->where('t.status', $request->query('status'));
        }

        $tickets = $query
            ->orderByRaw("FIELD(t.status, 'customer_reply', 'open', 'answered', 'closed')")
            ->orderByRaw("FIELD(t.priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByDesc('t.last_reply_at')
            ->paginate(40);

        $selected = null;
        $messages = collect();
        if ($request->filled('ticket')) {
            $selected = DB::table('nodexa_tickets as t')
                ->join('users as u', 'u.id', '=', 't.user_id')
                ->leftJoin('users as a', 'a.id', '=', 't.assigned_to')
                ->where('t.id', (int) $request->query('ticket'))
                ->first(['t.*', 'u.username', 'u.email', 'a.username as assigned_username']);

            if ($selected) {
                $messages = DB::table('nodexa_ticket_messages as m')
                    ->join('users as u', 'u.id', '=', 'm.user_id')
                    ->where('m.ticket_id', $selected->id)
                    ->orderBy('m.id')
                    ->get(['m.*', 'u.username']);
            }
        }

        return view('admin.tickets.index', compact('tickets', 'selected', 'messages'));
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $ticketRow = DB::table('nodexa_tickets')->where('id', $ticket)->first();
        abort_unless($ticketRow, 404);

        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $now = now();

        DB::transaction(function () use ($request, $ticket, $data, $now) {
            DB::table('nodexa_ticket_messages')->insert([
                'ticket_id' => $ticket,
                'user_id' => $request->user()->id,
                'message' => $data['message'],
                'is_staff' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('nodexa_tickets')->where('id', $ticket)->update([
                'status' => 'answered',
                'assigned_to' => $request->user()->id,
                'last_reply_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return redirect()->route('admin.tickets', ['ticket' => $ticket])->with('success', 'Svar sendt.');
    }

    public function update(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,answered,customer_reply,closed'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        DB::table('nodexa_tickets')->where('id', $ticket)->update([
            'status' => $data['status'],
            'priority' => $data['priority'],
            'assigned_to' => $request->user()->id,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.tickets', ['ticket' => $ticket])->with('success', 'Ticket opdateret.');
    }
}
