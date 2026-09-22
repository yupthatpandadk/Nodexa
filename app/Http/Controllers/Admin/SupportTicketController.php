<?php
namespace Pterodactyl\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\SupportTicket;
class SupportTicketController extends Controller {
 public function index(Request $r){$q=SupportTicket::with(['user','server'])->withCount('messages')->latest('last_reply_at');if($r->filled('status'))$q->where('status',$r->status);if($r->filled('priority'))$q->where('priority',$r->priority);return view('admin.tickets.index',['tickets'=>$q->limit(200)->get()]);}
 public function show(SupportTicket $ticket){$ticket->load(['user','server','messages.user']);return view('admin.tickets.show',compact('ticket'));}
 public function reply(Request $r,SupportTicket $ticket){$d=$r->validate(['message'=>'required|string|min:2|max:20000','status'=>'nullable|in:open,waiting_customer,answered,closed']);$ticket->messages()->create(['user_id'=>$r->user()->id,'staff'=>true,'message'=>$d['message']]);$status=$d['status']??'answered';$ticket->update(['status'=>$status,'last_reply_at'=>now(),'closed_at'=>$status==='closed'?now():null]);return back()->with('success','Svar sendt.');}
 public function update(Request $r,SupportTicket $ticket){$d=$r->validate(['status'=>'required|in:open,waiting_customer,answered,customer_reply,closed','priority'=>'required|in:low,normal,high,urgent','department'=>'required|in:support,billing,technical,sales']);$d['closed_at']=$d['status']==='closed'?now():null;$ticket->update($d);return back()->with('success','Ticket opdateret.');}
}