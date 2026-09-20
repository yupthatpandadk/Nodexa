<?php

namespace Pterodactyl\Models;

class Addon extends Model
{
    protected $table = 'nodexa_addons';

    protected $fillable = [
        'game', 'category', 'name', 'description', 'version', 'compatible_versions',
        'download_url', 'install_path', 'egg_ids', 'enabled',
    ];

    protected $casts = ['enabled' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
