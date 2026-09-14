<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

class DiscordBotController extends Controller
{
    public function __construct(private AlertsMessageBag $alert) {}

    public function index(): View
    {
        $bot = config('nodexa.discord_bot', []);
        $status = ['connected' => false, 'name' => null, 'avatar' => null, 'guild' => null, 'error' => null];
        $token = (string) ($bot['token'] ?? '');

        if (($bot['enabled'] ?? false) && $token !== '') {
            try {
                $user = Http::withHeaders(['Authorization' => 'Bot ' . $token])->timeout(5)->get('https://discord.com/api/v10/users/@me');
                if ($user->successful()) {
                    $json = $user->json();
                    $status['connected'] = true;
                    $status['name'] = ($json['username'] ?? 'Discord Bot') . (isset($json['discriminator']) && $json['discriminator'] !== '0' ? '#' . $json['discriminator'] : '');
                    if (!empty($json['id']) && !empty($json['avatar'])) $status['avatar'] = 'https://cdn.discordapp.com/avatars/' . $json['id'] . '/' . $json['avatar'] . '.png';
                    if (!empty($bot['guild_id'])) {
                        $guild = Http::withHeaders(['Authorization' => 'Bot ' . $token])->timeout(5)->get('https://discord.com/api/v10/guilds/' . $bot['guild_id']);
                        if ($guild->successful()) $status['guild'] = $guild->json('name');
                    }
                } else $status['error'] = 'Discord afviste bot-tokenet.';
            } catch (\Throwable $e) {
                $status['error'] = 'Kunne ikke forbinde til Discord.';
            }
        }

        return view('admin.discord-bot.index', [
            'bot' => $bot,
            'hasToken' => $token !== '',
            'status' => $status,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'token' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:100',
            'guild_id' => 'nullable|string|max:100',
            'status_channel_id' => 'nullable|string|max:100',
            'auto_role_id' => 'nullable|string|max:100',
            'presence' => 'nullable|string|max:128',
        ]);

        $values = [
            'NODEXA_DISCORD_BOT_ENABLED' => $request->boolean('enabled') ? 'true' : 'false',
            'NODEXA_DISCORD_BOT_CLIENT_ID' => trim((string) ($data['client_id'] ?? '')),
            'NODEXA_DISCORD_BOT_GUILD_ID' => trim((string) ($data['guild_id'] ?? '')),
            'NODEXA_DISCORD_BOT_STATUS_CHANNEL_ID' => trim((string) ($data['status_channel_id'] ?? '')),
            'NODEXA_DISCORD_BOT_AUTO_ROLE_ID' => trim((string) ($data['auto_role_id'] ?? '')),
            'NODEXA_DISCORD_BOT_PRESENCE' => trim((string) ($data['presence'] ?? 'Nodexa Hosting')),
        ];
        if (filled($data['token'] ?? null)) $values['NODEXA_DISCORD_BOT_TOKEN'] = trim($data['token']);
        $this->writeEnv($values);
        Artisan::call('config:clear');
        $this->alert->success('Discord Bot-indstillingerne er gemt.')->flash();
        return redirect()->route('admin.discord-bot');
    }

    public function test(): RedirectResponse
    {
        $token = (string) config('nodexa.discord_bot.token');
        if ($token === '') {
            $this->alert->danger('Der er ikke gemt et Bot Token.')->flash();
            return redirect()->route('admin.discord-bot');
        }
        try {
            $response = Http::withHeaders(['Authorization' => 'Bot ' . $token])->timeout(5)->get('https://discord.com/api/v10/users/@me');
            if ($response->successful()) $this->alert->success('Forbindelsen til Discord virker. Bot: ' . ($response->json('username') ?? 'ukendt'))->flash();
            else $this->alert->danger('Discord afviste bot-tokenet. Kontrollér tokenet i Discord Developer Portal.')->flash();
        } catch (\Throwable $e) {
            $this->alert->danger('Kunne ikke kontakte Discord API.')->flash();
        }
        return redirect()->route('admin.discord-bot');
    }

    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $contents = File::exists($path) ? File::get($path) : '';
        foreach ($values as $key => $value) {
            $escaped = '"' . addcslashes($value, "\\\"") . '"';
            $line = $key . '=' . $escaped;
            if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $contents)) $contents = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $contents);
            else $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
        }
        File::put($path, $contents);
    }
}
