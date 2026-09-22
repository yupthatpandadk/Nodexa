<?php
namespace Pterodactyl\Models;
class SupportTicketMessage extends Model {
 protected $table='nodexa_support_ticket_messages';
 protected $fillable=['ticket_id','user_id','staff','internal','message'];
 protected $casts=['staff'=>'boolean','internal'=>'boolean'];
 public function ticket(){return $this->belongsTo(SupportTicket::class,'ticket_id');}
 public function user(){return $this->belongsTo(User::class);}
 public function attachments(){return $this->hasMany(SupportTicketAttachment::class,'message_id');}
}