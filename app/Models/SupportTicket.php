<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $table = 'nodexa_support_tickets';

    protected $fillable = [
        'ticket_number', 'user_id', 'assigned_to', 'server_id', 'subject', 'department',
        'priority', 'status', 'tags', 'last_reply_at', 'first_response_at', 'due_at',
        'resolved_at', 'last_customer_reply_at', 'last_staff_reply_at', 'closed_at',
        'merged_into_id', 'reply_token', 'escalated_at', 'rating', 'feedback', 'rated_at',
    ];

    protected $casts = [
        'user_id' => 'integer', 'assigned_to' => 'integer', 'merged_into_id' => 'integer',
        'rating' => 'integer', 'last_reply_at' => 'datetime', 'first_response_at' => 'datetime',
        'due_at' => 'datetime', 'resolved_at' => 'datetime', 'last_customer_reply_at' => 'datetime',
        'last_staff_reply_at' => 'datetime', 'closed_at' => 'datetime',
        'escalated_at' => 'datetime', 'rated_at' => 'datetime',
    ];

    protected $hidden = ['reply_token'];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function departmentConfig(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department', 'key');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class, 'ticket_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }
}
