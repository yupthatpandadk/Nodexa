<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = DB::table('nodexa_tickets')
            ->where('user_id', $request->user()->id)
            ->orderByRaw("FIELD(status, 'customer_reply', 'open', 'answered', 'closed')")
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->get();

        $selected = null;
        $messages = collect();
        if ($request->filled('ticket')) {
            $selected = DB::table('nodexa_tickets')
                ->where('id', (int) $request->query('ticket'))
                ->where('user_id', $request->user()->id)
                ->first();

            if ($selected) {
                $messages = DB::table('nodexa_ticket_messages as m')
                    ->join('users as u', 'u.id', '=', 'm.user_id')
                    ->where('m.ticket_id', $selected->id)
                    ->orderBy('m.id')
                    ->get(['m.*', 'u.username']);
            }
        }

        return view('tickets.index', compact('tickets', 'selected', 'messages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['required', 'in:support,billing,server,other'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $ticketId = DB::transaction(function () use ($request, $data) {
            $now = now();
            $id = DB::table('nodexa_tickets')->insertGetId([
                'user_id' => $request->user()->id,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => 'open',
                'last_reply_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('nodexa_ticket_messages')->insert([
                'ticket_id' => $id,
                'user_id' => $request->user()->id,
                'message' => $data['message'],
                'is_staff' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $id;
        });

        return redirect()->route('tickets.index', ['ticket' => $ticketId])->with('success', 'Din ticket er oprettet.');
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $ticketRow = DB::table('nodexa_tickets')
            ->where('id', $ticket)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($ticketRow, 404);
        abort_if($ticketRow->status === 'closed', 422, 'Ticketen er lukket.');

        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $now = now();

        DB::transaction(function () use ($request, $ticket, $data, $now) {
            DB::table('nodexa_ticket_messages')->insert([
                'ticket_id' => $ticket,
                'user_id' => $request->user()->id,
                'message' => $data['message'],
                'is_staff' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('nodexa_tickets')->where('id', $ticket)->update([
                'status' => 'customer_reply',
                'last_reply_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return redirect()->route('tickets.index', ['ticket' => $ticket])->with('success', 'Svar sendt.');
    }

    public function close(Request $request, int $ticket): RedirectResponse
    {
        $updated = DB::table('nodexa_tickets')
            ->where('id', $ticket)
            ->where('user_id', $request->user()->id)
            ->update(['status' => 'closed', 'updated_at' => now()]);

        abort_unless($updated, 404);
        return redirect()->route('tickets.index', ['ticket' => $ticket])->with('success', 'Ticket lukket.');
    }
}
