<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Allocation extends Model
{
    protected $fillable = ['node_id','server_id','ip','port','alias','notes','is_primary'];
    protected $casts = ['port'=>'integer','is_primary'=>'boolean'];

    public function node() { return $this->belongsTo(Node::class); }
    public function server() { return $this->belongsTo(Server::class); }
}
