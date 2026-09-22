<?php
namespace Pterodactyl\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pterodactyl\Models\SupportTicket;
class SupportTicketController extends Controller {
 public function index(Request $r){return view('store.client.tickets.index',['tickets'=>SupportTicket::where('user_id',$r->user()->id)->with('server')->latest('last_reply_at')->latest()->get()]);}
 public function create(Request $r){return view('store.client.tickets.create',['servers'=>$r->user()->servers()->orderBy('name')->get()]);}
 public function store(Request $r){$d=$r->validate(['subject'=>'required|string|max:160','department'=>'required|in:support,billing,technical,sales','priority'=>'required|in:low,normal,high,urgent','server_id'=>'nullable|integer','message'=>'required|string|min:10|max:20000']);if(!empty($d['server_id'])&&!$r->user()->servers()->whereKey($d['server_id'])->exists())abort(403);$ticket=SupportTicket::create(['ticket_number'=>'NX-'.strtoupper(Str::random(8)),'user_id'=>$r->user()->id,'server_id'=>$d['server_id']??null,'subject'=>$d['subject'],'department'=>$d['department'],'priority'=>$d['priority'],'status'=>'open','last_reply_at'=>now()]);$ticket->messages()->create(['user_id'=>$r->user()->id,'staff'=>false,'message'=>$d['message']]);return redirect()->route('store.client.tickets.show',$ticket)->with('success','Ticket oprettet.');}
 public function show(Request $r,SupportTicket $ticket){abort_unless($ticket->user_id===$r->user()->id,403);$ticket->load(['messages.user','server']);return view('store.client.tickets.show',compact('ticket'));}
 public function reply(Request $r,SupportTicket $ticket){abort_unless($ticket->user_id===$r->user()->id,403);abort_if($ticket->status==='closed',422);$d=$r->validate(['message'=>'required|string|min:2|max:20000']);$ticket->messages()->create(['user_id'=>$r->user()->id,'staff'=>false,'message'=>$d['message']]);$ticket->update(['status'=>'customer_reply','last_reply_at'=>now()]);return back()->with('success','Svar sendt.');}
 public function close(Request $r,SupportTicket $ticket){abort_unless($ticket->user_id===$r->user()->id,403);$ticket->update(['status'=>'closed','closed_at'=>now()]);return back()->with('success','Ticket lukket.');}
}