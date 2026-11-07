<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\KnowledgebaseArticle;
use Pterodactyl\Models\SupportDepartment;
use Pterodactyl\Models\SupportSetting;
use Pterodactyl\Models\SupportTicket;
use Pterodactyl\Services\Support\TicketWorkflow;

class SupportTicketController extends Controller
{
    public function __construct(private TicketWorkflow $workflow)
    {
    }

    private function owner(Request $r, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $r->user()->id, 403);
    }

    public function index(Request $r)
    {
        $q = SupportTicket::where('user_id', $r->user()->id)->with('server');
        if ($r->filled('status')) {
            $q->where('status', $r->string('status')->toString());
        }
        if ($r->filled('q')) {
            $term = '%' . mb_substr($r->string('q')->toString(), 0, 160) . '%';
            $q->where(fn ($q) => $q->where('subject', 'like', $term)->orWhere('ticket_number', 'like', $term));
        }
        return view('store.client.tickets.index', ['tickets' => $q->latest('last_reply_at')->paginate(25)->withQueryString()]);
    }

    public function create(Request $r)
    {
        return view('store.client.tickets.create', ['servers' => $r->user()->servers()->orderBy('name')->get(), 'departments' => SupportDepartment::where('enabled', true)->where('hidden', false)->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'subject' => 'required|string|max:160',
            'department' => ['required', Rule::exists('nodexa_support_departments', 'key')->where('enabled', true)->where('hidden', false)],
            'priority' => ['required', Rule::in(TicketWorkflow::PRIORITIES)], 'server_id' => 'nullable|integer',
            'message' => 'required|string|min:10|max:20000', ...TicketWorkflow::FILE_RULES,
        ]);
        if (!empty($d['server_id'])) {
            abort_unless($r->user()->servers()->whereKey($d['server_id'])->exists(), 403);
        }
        $ticket = $this->workflow->create($r->user(), $d, $r->file('attachments', []));
        return redirect()->route('store.client.tickets.show', $ticket);
    }

    public function show(Request $r, SupportTicket $ticket)
    {
        $this->owner($r, $ticket);
        if ($ticket->merged_into_id) {
            return redirect()->route('store.client.tickets.show', $ticket->merged_into_id);
        }
        $ticket->load(['messages' => fn ($q) => $q->where('internal', false)->orderBy('created_at')->orderBy('id')->with(['user', 'attachments']), 'server', 'assignee', 'departmentConfig']);
        return view('store.client.tickets.show', compact('ticket'));
    }

    public function reply(Request $r, SupportTicket $ticket)
    {
        $this->owner($r, $ticket);
        $d = $r->validate(['message' => 'required|string|min:2|max:20000', ...TicketWorkflow::FILE_RULES]);
        $this->workflow->reply($ticket, $r->user(), $d['message'], files: $r->file('attachments', []));
        return back()->with('success', 'Svar sendt.');
    }

    public function close(Request $r, SupportTicket $ticket)
    {
        $this->owner($r, $ticket);
        abort_if($ticket->departmentConfig?->prevent_client_close, 403, 'Only support staff can close this ticket.');
        $this->workflow->changeStatus($ticket, $r->user(), 'closed', 'Closed by customer', customer: true);
        return back();
    }

    public function reopen(Request $r, SupportTicket $ticket)
    {
        $this->owner($r, $ticket);
        abort_unless(SupportSetting::value('allow_reopen', '1') === '1' && $ticket->status === 'closed', 422);
        $this->workflow->changeStatus($ticket, $r->user(), 'open', 'Reopened by customer', customer: true);
        return back();
    }

    public function feedback(Request $r, SupportTicket $ticket)
    {
        $this->owner($r, $ticket);
        $d = $r->validate(['rating' => 'required|integer|between:1,5', 'feedback' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($ticket, $d) {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($ticket->status === 'closed' && !$ticket->merged_into_id, 422);
            $ticket->update([...$d, 'rated_at' => now()]);
        });
        return back()->with('success', 'Tak for din bedømmelse.');
    }

    public function rateReply(Request $r, SupportTicket $ticket, int $message)
    {
        $this->owner($r, $ticket);
        $d = $r->validate(['rating' => 'required|integer|between:1,5']);
        $reply = $ticket->messages()->where('staff', true)->where('internal', false)->findOrFail($message);
        $reply->update(['rating' => $d['rating'], 'rated_at' => now()]);
        return back()->with('success', 'Tak for din bedømmelse.');
    }

    public function suggestions(Request $r)
    {
        $d = $r->validate(['q' => 'required|string|min:3|max:160']);
        $words = array_values(array_filter(array_slice(preg_split('/\s+/u', $d['q'], -1, PREG_SPLIT_NO_EMPTY), 0, 6), fn ($word) => mb_strlen($word) >= 3));
        if (!$words) {
            return response()->json([]);
        }
        $articles = KnowledgebaseArticle::where('published', true)->whereHas('category', fn ($q) => $q->where('published', true))
            ->where(function ($q) use ($words) { foreach ($words as $word) { if (mb_strlen($word) >= 3) { $q->orWhere('title', 'like', '%' . addcslashes($word, '%_\\') . '%'); } } })
            ->orderByDesc('views')->limit(4)->get();
        return response()->json($articles->map(fn ($a) => ['title' => $a->title, 'url' => route('knowledgebase.article', $a)]));
    }

    public function attachment(Request $r, SupportTicket $ticket, int $attachment)
    {
        $this->owner($r, $ticket);
        $a = $ticket->attachments()->whereHas('message', fn ($q) => $q->where('internal', false))->findOrFail($attachment);
        return Storage::disk($a->disk)->download($a->path, $a->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
