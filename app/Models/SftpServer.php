<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

class SftpServer extends Model
{
    protected $table = 'sftp_servers';

    protected $fillable = [
        'uuid', 'name', 'host', 'port', 'username', 'password', 'root_path', 'enabled',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'enabled' => 'boolean',
            'port' => 'integer',
        ];
    }
}
