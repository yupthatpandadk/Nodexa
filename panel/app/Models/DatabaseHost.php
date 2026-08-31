<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class DatabaseHost extends Model
{
    protected $fillable = ['name','host','port','username','password','remote_host','node_id','max_databases','ssl','enabled','last_checked_at','last_status','last_error'];
    protected $hidden = ['password'];
    protected $casts = [
        'port'=>'integer','node_id'=>'integer','max_databases'=>'integer','ssl'=>'boolean','enabled'=>'boolean','last_checked_at'=>'datetime'
    ];

    public function node() { return $this->belongsTo(Node::class); }
    public function databases() { return $this->hasMany(ServerDatabase::class); }
    public function setPasswordAttribute(string $value): void { $this->attributes['password'] = Crypt::encryptString($value); }
    public function plainPassword(): string { return Crypt::decryptString($this->attributes['password']); }
}
