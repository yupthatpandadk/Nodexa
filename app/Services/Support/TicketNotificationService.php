<?php
namespace Pterodactyl\Services\Support;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Pterodactyl\Models\SupportSetting;
use Pterodactyl\Models\SupportTicket;
class TicketNotificationService {
 private function mail($user,string $subject,string $body): void {
  if(!$user || !filter_var($user->email,FILTER_VALIDATE_EMAIL)) return;
  try {Mail::send('emails.nodexa-message',['recipient'=>$user->name ?: $user->username,'body'=>$body],fn($m)=>$m->to($user->email)->subject($subject));}
  catch(\Throwable $e){Log::warning('Ticket email failed',['error'=>$e->getMessage()]);}
 }
 private function discord(string $title,string $description,int $color=3447003): void {
  $url=SupportSetting::value('discord_webhook'); if(!$url) return;
  try {Http::timeout(5)->post($url,['username'=>'Nodexa Support','embeds'=>[['title'=>$title,'description'=>$description,'color'=>$color,'timestamp'=>now()->toIso8601String()]]]);}
  catch(\Throwable $e){Log::warning('Ticket Discord webhook failed',['error'=>$e->getMessage()]);}
 }
 public function created(SupportTicket $t): void {$t->loadMissing('user');$url=route('admin.tickets.show',$t);$this->mail($t->user,"[{$t->ticket_number}] Ticket modtaget","Hej {$t->user->name},\n\nVi har modtaget din ticket: {$t->subject}.\nTicket: {$t->ticket_number}\nStatus: Open\n\nFølg sagen: ".route('store.client.tickets.show',$t)."\n\nNodexa Support");$this->discord("Ny ticket · {$t->ticket_number}","{$t->subject}\nAfdeling: {$t->department}\nPrioritet: {$t->priority}\n{$url}",15105570);}
 public function customerReply(SupportTicket $t): void {$this->discord("Kundesvar · {$t->ticket_number}","{$t->subject}\n".route('admin.tickets.show',$t),15844367);}
 public function staffReply(SupportTicket $t): void {$t->loadMissing('user');$this->mail($t->user,"[{$t->ticket_number}] Nyt svar fra Nodexa","Hej {$t->user->name},\n\nDer er kommet et nyt svar på: {$t->subject}.\n\nLæs og svar: ".route('store.client.tickets.show',$t)."\n\nNodexa Support");}
 public function closed(SupportTicket $t): void {$t->loadMissing('user');$this->mail($t->user,"[{$t->ticket_number}] Ticket lukket","Din ticket {$t->ticket_number} · {$t->subject} er blevet lukket.\n\nDu kan se ticket-historikken i Client Area.\n\nNodexa Support");}
}