<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\AdvancedSettingsFormRequest;

class AdvancedController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private ConfigRepository $config, private Kernel $kernel, private SettingsRepositoryInterface $settings) {}

    public function index(): View
    {
        $showRecaptchaWarning = $this->config->get('recaptcha._shipped_secret_key') === $this->config->get('recaptcha.secret_key') || $this->config->get('recaptcha._shipped_website_key') === $this->config->get('recaptcha.website_key');
        return view('admin.settings.advanced', ['showRecaptchaWarning' => $showRecaptchaWarning]);
    }

    public function update(AdvancedSettingsFormRequest $request): RedirectResponse
    {
        $data = $request->normalize();
        foreach ($data as $key => $value) $this->settings->set('settings::' . $key, $value);

        $limit = (int) ($data['nodexa:upload_limit_mb'] ?? 2048);
        $result = $this->applySystemUploadLimit($limit);
        $this->kernel->call('queue:restart');

        if ($result['ok']) {
            $this->alert->success("Advanced settings updated. Upload limit is now {$limit} MB for Nodexa, PHP and Nginx.")->flash();
        } else {
            $this->alert->warning("Nodexa saved {$limit} MB, but the system upload limit could not be applied automatically: {$result['message']}")->flash();
        }
        return redirect()->route('admin.settings.advanced');
    }

    private function applySystemUploadLimit(int $mb): array
    {
        $script = base_path('scripts/apply-upload-limit.sh');
        if (!is_file($script)) return ['ok' => false, 'message' => 'apply-upload-limit.sh is missing'];
        $command = 'sudo -n ' . escapeshellarg($script) . ' ' . escapeshellarg((string) $mb) . ' 2>&1';
        exec($command, $output, $code);
        return ['ok' => $code === 0, 'message' => trim(implode("\n", $output)) ?: 'unknown system error'];
    }
}
