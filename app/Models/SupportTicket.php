<?php
namespace Pterodactyl\Models;
class SupportTicket extends Model {
 protected $table='nodexa_support_tickets';
 protected $fillable=['ticket_number','user_id','server_id','subject','department','priority','status','last_reply_at','closed_at'];
 protected $casts=['last_reply_at'=>'datetime','closed_at'=>'datetime'];
 public function user(){return $this->belongsTo(User::class);}
 public function server(){return $this->belongsTo(Server::class);}
 public function messages(){return $this->hasMany(SupportTicketMessage::class,'ticket_id');}
}