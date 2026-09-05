<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientApiKeyController extends Controller
{
    private const PREFIX = 'nxa_';
    private const SECRET_LENGTH = 48;
    private const NAME_PREFIX = 'client-api:';

    public function index(Request $request)
    {
        return $request->user()->tokens()
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->latest('id')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => Str::after((string) $token->name, self::NAME_PREFIX),
                'prefix' => self::PREFIX,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ])
            ->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:64',
        ]);

        $label = trim((string) ($data['name'] ?? 'Nodexa Client API'));
        if ($label === '') {
            $label = 'Nodexa Client API';
        }

        do {
            $plainTextToken = self::PREFIX.Str::random(self::SECRET_LENGTH);
            $hash = hash('sha256', $plainTextToken);
        } while (DB::table('personal_access_tokens')->where('token', $hash)->exists());

        $token = $request->user()->tokens()->create([
            'name' => self::NAME_PREFIX.$label,
            'token' => $hash,
            'abilities' => ['*'],
        ]);

        return response()->json([
            'id' => $token->id,
            'name' => $label,
            'token' => $plainTextToken,
            'prefix' => self::PREFIX,
            'created_at' => $token->created_at,
            'message' => 'Gem denne Nodexa API-nøgle nu. Den fulde nøgle vises kun én gang.',
        ], 201);
    }

    public function destroy(Request $request, int $token)
    {
        $deleted = $request->user()->tokens()
            ->whereKey($token)
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->delete();

        abort_if($deleted === 0, 404, 'Nodexa Client API key not found.');

        return response()->noContent();
    }

    public function info(Request $request)
    {
        return [
            'system' => 'Nodexa Client API',
            'prefix' => self::PREFIX,
            'authenticated' => true,
            'user_id' => $request->user()->id,
            'endpoints' => [
                'servers' => '/api/servers',
                'me' => '/api/me',
            ],
        ];
    }
}
