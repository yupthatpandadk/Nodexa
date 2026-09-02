<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $fillable = ['name','fqdn','scheme','daemon_port','sftp_port','token','memory_mb','disk_mb','location','setup_options'];
    protected $hidden = ['token'];
    protected $casts = [
        'daemon_port'=>'integer',
        'sftp_port'=>'integer',
        'memory_mb'=>'integer',
        'disk_mb'=>'integer',
        'setup_options'=>'array',
    ];

    public function servers() { return $this->hasMany(Server::class); }
    public function databaseHosts() { return $this->hasMany(DatabaseHost::class); }
    public function allocations() { return $this->hasMany(Allocation::class); }
}
