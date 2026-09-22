<?php
namespace Pterodactyl\Models;
class SupportTicketEvent extends Model {
 protected $table='nodexa_support_ticket_events';
 protected $fillable=['ticket_id','user_id','event','details'];
 public function user(){return $this->belongsTo(User::class);}
}