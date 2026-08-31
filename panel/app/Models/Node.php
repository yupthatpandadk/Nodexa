<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $fillable = ['name','fqdn','scheme','daemon_port','sftp_port','token','memory_mb','disk_mb','location'];
    protected $hidden = ['token'];
    protected $casts = ['daemon_port'=>'integer','sftp_port'=>'integer','memory_mb'=>'integer','disk_mb'=>'integer'];

    public function servers() { return $this->hasMany(Server::class); }
}
