<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'nullable|string|max:255|required_without:email',
            'email' => 'nullable|string|max:255|required_without:login',
            'password' => 'required|string',
        ]);

        $identifier = strtolower(trim($data['login'] ?? $data['email'] ?? ''));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$identifier])
            ->orWhereRaw('LOWER(username) = ?', [$identifier])
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Forkert e-mail/brugernavn eller adgangskode.'], 422);
        }

        $user->tokens()->where('name', 'panel')->delete();
        $token = $user->createToken('panel')->plainTextToken;

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
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $name,
                'email' => $user->email,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'is_admin' => (bool) $user->is_admin,
            ],
        ];
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->noContent();
    }
}
