<?php
namespace App\Http\Controllers;

use App\Models\User;
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

    public function adminIndex(Request $request)
    {
        abort_unless((bool) $request->user()?->is_admin, 403, 'Administrator permission required.');

        $query = trim((string) $request->query('query', ''));

        return User::query()
            ->when($query !== '', function ($builder) use ($query) {
                $like = '%'.$query.'%';
                $builder->where(function ($q) use ($like) {
                    $q->where('email', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
            })
            ->orderBy('email')
            ->limit(100)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
            ])
            ->values();
    }
}
