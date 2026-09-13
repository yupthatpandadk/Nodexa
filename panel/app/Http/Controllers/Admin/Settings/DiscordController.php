<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

class DiscordController extends Controller
{
    public function __construct(private AlertsMessageBag $alert) {}

    public function index(): View
    {
        return view('admin.settings.discord', [
            'enabled' => (bool) config('nodexa.discord.enabled'),
            'clientId' => (string) config('nodexa.discord.client_id'),
            'redirectUri' => (string) (config('nodexa.discord.redirect_uri') ?: url('/auth/discord/callback')),
            'afterLogin' => (string) config('nodexa.discord.after_login', '/client'),
            'hasSecret' => filled(config('nodexa.discord.client_secret')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'client_id' => 'nullable|string|max:100',
            'client_secret' => 'nullable|string|max:255',
            'redirect_uri' => 'required|url|max:255',
            'after_login' => 'required|string|max:255',
        ]);

        $this->writeEnv([
            'NODEXA_DISCORD_LOGIN_ENABLED' => $request->boolean('enabled') ? 'true' : 'false',
            'NODEXA_DISCORD_CLIENT_ID' => trim((string) ($data['client_id'] ?? '')),
            'NODEXA_DISCORD_REDIRECT_URI' => trim($data['redirect_uri']),
            'NODEXA_DISCORD_AFTER_LOGIN' => trim($data['after_login']),
        ]);
        if (filled($data['client_secret'] ?? null)) {
            $this->writeEnv(['NODEXA_DISCORD_CLIENT_SECRET' => trim($data['client_secret'])]);
        }

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        $this->alert->success('Discord login-indstillingerne er gemt.')->flash();
        return redirect()->route('admin.settings.discord');
    }

    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $contents = File::exists($path) ? File::get($path) : '';
        foreach ($values as $key => $value) {
            $escaped = '"' . addcslashes($value, "\\\"") . '"';
            $line = $key . '=' . $escaped;
            if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $contents)) {
                $contents = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $contents);
            } else {
                $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
            }
        }
        File::put($path, $contents);
    }
}
