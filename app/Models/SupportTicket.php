<?php
namespace Pterodactyl\Models;
class SupportTicket extends Model {
 protected $table='nodexa_support_tickets';
 protected $fillable=['ticket_number','user_id','assigned_to','server_id','subject','department','priority','status','tags','last_reply_at','first_response_at','due_at','resolved_at','last_customer_reply_at','last_staff_reply_at','closed_at'];
 protected $casts=['last_reply_at'=>'datetime','first_response_at'=>'datetime','due_at'=>'datetime','resolved_at'=>'datetime','last_customer_reply_at'=>'datetime','last_staff_reply_at'=>'datetime','closed_at'=>'datetime'];
 public function user(){return $this->belongsTo(User::class);}
 public function server(){return $this->belongsTo(Server::class);}
 public function assignee(){return $this->belongsTo(User::class,'assigned_to');}
 public function attachments(){return $this->hasMany(SupportTicketAttachment::class,'ticket_id');}
 public function events(){return $this->hasMany(SupportTicketEvent::class,'ticket_id');}
 public function messages(){return $this->hasMany(SupportTicketMessage::class,'ticket_id');}
}