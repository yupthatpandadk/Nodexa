<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;

class DiscordLoginController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless(config('nodexa.discord.enabled') && config('nodexa.discord.client_id') && config('nodexa.discord.client_secret'), 404);
        $state = Str::random(48);
        $request->session()->put('discord_oauth_state', $state);
        $query = http_build_query([
            'client_id' => config('nodexa.discord.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
            'prompt' => 'consent',
        ]);
        return redirect()->away('https://discord.com/oauth2/authorize?' . $query);
    }

    public function callback(Request $request)
    {
        abort_unless(config('nodexa.discord.enabled'), 404);
        $state = (string) $request->session()->pull('discord_oauth_state');
        if (!$state || !hash_equals($state, (string) $request->query('state'))) {
            return redirect('/auth/login')->with('error', 'Discord login kunne ikke valideres. Prøv igen.');
        }
        if (!$request->filled('code')) return redirect('/auth/login')->with('error', 'Discord login blev annulleret.');

        try {
            $token = Http::asForm()->timeout(12)->post('https://discord.com/api/oauth2/token', [
                'client_id' => config('nodexa.discord.client_id'),
                'client_secret' => config('nodexa.discord.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
                'redirect_uri' => $this->redirectUri(),
            ])->throw()->json();
            $discord = Http::withToken($token['access_token'])->acceptJson()->timeout(12)->get('https://discord.com/api/users/@me')->throw()->json();
        } catch (\Throwable $e) {
            report($e);
            return redirect('/auth/login')->with('error', 'Discord kunne ikke kontaktes. Prøv igen.');
        }

        $discordId = (string) ($discord['id'] ?? '');
        $email = strtolower(trim((string) ($discord['email'] ?? '')));
        if (!$discordId || !$email || empty($discord['verified'])) {
            return redirect('/auth/login')->with('error', 'Din Discord-konto skal have en verificeret e-mailadresse.');
        }

        $externalId = 'discord:' . $discordId;
        $user = User::query()->where('external_id', $externalId)->first();
        if (!$user) {
            // Safely link an existing Nodexa account only when Discord has verified the same email.
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) {
                if ($user->external_id && $user->external_id !== $externalId) {
                    return redirect('/auth/login')->with('error', 'Denne konto er allerede koblet til en anden ekstern loginmetode.');
                }
                $user->external_id = $externalId;
                $user->save();
            } else {
                $base = Str::lower(preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($discord['username'] ?? 'discord')) ?: 'discord');
                $username = substr($base, 0, 170);
                $n = 0;
                while (User::query()->where('username', $username)->exists()) $username = substr($base, 0, 160) . '-' . (++$n);
                $display = trim((string) ($discord['global_name'] ?? $discord['username'] ?? 'Discord User'));
                $parts = preg_split('/\s+/', $display, 2);
                $user = User::query()->create([
                    'external_id' => $externalId,
                    'username' => $username,
                    'email' => $email,
                    'name_first' => substr($parts[0] ?: 'Discord', 0, 191),
                    'name_last' => substr($parts[1] ?? 'User', 0, 191),
                    'password' => password_hash(Str::random(64), PASSWORD_DEFAULT),
                    'language' => config('app.locale', 'en'),
                    'root_admin' => false,
                ]);
            }
        }

        Auth::guard()->login($user, true);
        $request->session()->regenerate();
        return redirect()->intended(config('nodexa.discord.after_login', '/client'));
    }

    private function redirectUri(): string
    {
        $configured = trim((string) config('nodexa.discord.redirect_uri'));
        return $configured !== '' ? $configured : url('/auth/discord/callback');
    }
}
