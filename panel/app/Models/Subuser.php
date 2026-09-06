<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subuser extends Model
{
    protected $fillable = ['server_id','user_id','permissions'];
    protected $casts = ['permissions'=>'array'];
    public function server() { return $this->belongsTo(Server::class); }
    public function user() { return $this->belongsTo(User::class); }
}
