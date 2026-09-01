<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        $name = trim((string) ($user->name ?? ''));
        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                (string) ($user->first_name ?? ''),
                (string) ($user->last_name ?? ''),
            ])));
        }
        if ($name === '') {
            $name = trim((string) ($user->username ?? ''));
        }
        if ($name === '') {
            $name = trim((string) ($user->email ?? 'Nodexa User'));
        }

        return [
            'id' => $user->id,
            'name' => $name,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
        ];
    }
}
