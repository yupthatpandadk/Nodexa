<?php

namespace Pterodactyl\Models;

class SupportCannedReply extends Model
{
    protected $table = 'nodexa_support_canned_replies';
    protected $fillable = ['department_id', 'title', 'body'];
    protected $casts = ['department_id' => 'integer'];
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
