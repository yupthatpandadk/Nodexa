<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Http\Controllers\Controller;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = collect();
        if (Schema::hasTable('tickets')) {
            $query = DB::table('tickets as t')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->select('t.*', 'u.username', 'u.email');

            if ($request->filled('status')) $query->where('t.status', $request->input('status'));
            if ($request->filled('department')) $query->where('t.department', $request->input('department'));
            if ($request->filled('priority')) $query->where('t.priority', $request->input('priority'));
            if ($request->filled('search')) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('t.subject', 'like', $search)
                        ->orWhere('u.username', 'like', $search)
                        ->orWhere('u.email', 'like', $search)
                        ->orWhere('t.id', 'like', $search);
                });
            }
            $tickets = $query->orderByRaw("CASE WHEN t.status = 'customer_reply' THEN 0 WHEN t.status = 'open' THEN 1 WHEN t.status = 'answered' THEN 2 ELSE 3 END")
                ->orderByDesc('t.updated_at')->limit(250)->get();
        }

        $stats = [
            'open' => $tickets->where('status', 'open')->count(),
            'waiting' => $tickets->where('status', 'customer_reply')->count(),
            'answered' => $tickets->where('status', 'answered')->count(),
            'closed' => $tickets->where('status', 'closed')->count(),
        ];

        return view('admin.commerce.tickets', compact('tickets', 'stats'));
    }

    public function show(int $id)
    {
        $ticket = DB::table('tickets as t')->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->select('t.*', 'u.username', 'u.email', 'u.name_first', 'u.name_last')->where('t.id', $id)->first();
        abort_unless($ticket, 404);
        $messages = DB::table('ticket_messages as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'u.username', 'u.email')->where('m.ticket_id', $id)->orderBy('m.id')->get();
        return view('admin.commerce.ticket', compact('ticket', 'messages'));
    }

    public function reply(Request $request, int $id)
    {
        $ticket = DB::table('tickets')->where('id', $id)->first();
        abort_unless($ticket, 404);
        $data = $request->validate(['message' => 'required|string|max:10000']);
        DB::transaction(function () use ($request, $id, $data) {
            DB::table('ticket_messages')->insert([
                'ticket_id' => $id, 'user_id' => $request->user()->id, 'message' => $data['message'],
                'staff' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('tickets')->where('id', $id)->update(['status' => 'answered', 'updated_at' => now()]);
        });
        return back()->with('success', 'Svaret er sendt til kunden.');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:open,customer_reply,answered,closed',
            'priority' => 'required|in:low,normal,high',
            'department' => 'required|in:support,billing,sales',
        ]);
        abort_unless(DB::table('tickets')->where('id', $id)->exists(), 404);
        DB::table('tickets')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'Ticketen blev opdateret.');
    }

    public function close(int $id)
    {
        abort_unless(DB::table('tickets')->where('id', $id)->exists(), 404);
        DB::table('tickets')->where('id', $id)->update(['status' => 'closed', 'updated_at' => now()]);
        return back()->with('success', 'Ticketen blev lukket.');
    }

    public function reopen(int $id)
    {
        abort_unless(DB::table('tickets')->where('id', $id)->exists(), 404);
        DB::table('tickets')->where('id', $id)->update(['status' => 'open', 'updated_at' => now()]);
        return back()->with('success', 'Ticketen blev genåbnet.');
    }
}
