<?php
namespace Pterodactyl\Models;
class SupportTicketAttachment extends Model {
 protected $table='nodexa_support_ticket_attachments';
 protected $fillable=['ticket_id','message_id','user_id','disk','path','original_name','mime','size'];
 public function ticket(){return $this->belongsTo(SupportTicket::class,'ticket_id');}
 public function message(){return $this->belongsTo(SupportTicketMessage::class,'message_id');}
}