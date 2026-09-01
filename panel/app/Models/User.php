<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'first_name',
        'last_name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Always expose a usable display name to the frontend, including for
     * accounts created before first_name/last_name/name were introduced.
     */
    public function getNameAttribute($value): string
    {
        $name = trim((string) $value);
        if ($name !== '') {
            return $name;
        }

        $fullName = trim(implode(' ', array_filter([
            trim((string) ($this->attributes['first_name'] ?? '')),
            trim((string) ($this->attributes['last_name'] ?? '')),
        ])));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string) ($this->attributes['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        $email = trim((string) ($this->attributes['email'] ?? ''));
        if ($email !== '') {
            return strstr($email, '@', true) ?: $email;
        }

        return 'Nodexa User';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
