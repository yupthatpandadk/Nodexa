<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['name', 'slug', 'description', 'permissions', 'color'];

    protected $casts = ['permissions' => 'array'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array('*', $this->permissions ?? [], true)
            || in_array($permission, $this->permissions ?? [], true);
    }
}
