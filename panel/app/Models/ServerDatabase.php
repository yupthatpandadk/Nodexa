<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ServerDatabase extends Model
{
    protected $fillable = ['server_id','database_host_id','name','username','password','host','port'];
    protected $hidden = ['password'];
    protected $casts = ['port'=>'integer','database_host_id'=>'integer'];

    public function server() { return $this->belongsTo(Server::class); }
    public function databaseHost() { return $this->belongsTo(DatabaseHost::class); }
    public function setPasswordAttribute(string $value): void { $this->attributes['password'] = Crypt::encryptString($value); }
    public function plainPassword(): string { return Crypt::decryptString($this->attributes['password']); }
}
