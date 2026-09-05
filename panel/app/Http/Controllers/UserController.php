<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * List the current user's Nodexa client API keys.
     * The actual token secret is never returned after creation.
     */
    public function apiKeys(Request $request)
    {
        return $request->user()->tokens()
            ->where('name', 'like', 'client-api:%')
            ->latest('id')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => Str::after((string) $token->name, 'client-api:'),
                'prefix' => 'nxa_',
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ])
            ->values();
    }

    /**
     * Create a Nodexa client API key in the same recognizable style as
     * Pterodactyl's ptla_ keys, but with Nodexa's own nxa_ prefix.
     * Only a SHA-256 hash is persisted. The plain token is shown once.
     */
    public function createApiKey(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:64',
        ]);

        $label = trim((string) ($data['name'] ?? 'Mobile / Client API'));
        if ($label === '') {
            $label = 'Mobile / Client API';
        }

        do {
            $plainTextToken = 'nxa_'.Str::random(48);
            $hash = hash('sha256', $plainTextToken);
            $exists = DB::table('personal_access_tokens')->where('token', $hash)->exists();
        } while ($exists);

        $token = $request->user()->tokens()->create([
            'name' => 'client-api:'.$label,
            'token' => $hash,
            'abilities' => ['*'],
        ]);

        return response()->json([
            'id' => $token->id,
            'name' => $label,
            'token' => $plainTextToken,
            'prefix' => 'nxa_',
            'created_at' => $token->created_at,
            'message' => 'Gem API-nøglen nu. Af sikkerhedsgrunde vises den kun denne ene gang.',
        ], 201);
    }

    public function deleteApiKey(Request $request, int $token)
    {
        $deleted = $request->user()->tokens()
            ->whereKey($token)
            ->where('name', 'like', 'client-api:%')
            ->delete();

        abort_if($deleted === 0, 404, 'API key not found.');

        return response()->noContent();
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
