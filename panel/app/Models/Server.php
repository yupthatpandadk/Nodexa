<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Server extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['uuid','server_number','identifier','name','owner_id','node_id','template_id','docker_image','startup','memory_mb','disk_mb','cpu_limit','status','environment'];
    protected $casts = ['environment'=>'array','memory_mb'=>'integer','disk_mb'=>'integer','cpu_limit'=>'integer','server_number'=>'integer'];

    public function node() { return $this->belongsTo(Node::class); }
    public function databases() { return $this->hasMany(ServerDatabase::class); }
}
