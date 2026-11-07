<?php

namespace Pterodactyl\Models;

class SupportDepartment extends Model
{
    protected $table = 'nodexa_support_departments';
    protected $guarded = ['id'];
    protected $casts = ['enabled' => 'boolean', 'hidden' => 'boolean', 'prevent_client_close' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
    public function staff()
    {
        return $this->belongsToMany(User::class, 'nodexa_support_department_user', 'department_id', 'user_id');
    }
}
