<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Controllers\Controller;

class SiteAccessController extends Controller
{
    public const PREFIX = 'nodexa::site_access.';

    public function __construct(
        private AlertsMessageBag $alert,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    public function index(): View
    {
        $target = $this->settings->get(self::PREFIX . 'countdown_target', '');
        $targetForInput = '';

        if ($target) {
            try {
                $targetForInput = Carbon::parse($target)
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d\\TH:i');
            } catch (\Throwable) {
                $targetForInput = '';
            }
        }

        return view('admin.site-access.index', [
            'countdownEnabled' => $this->boolean('countdown_enabled'),
            'countdownTitle' => $this->settings->get(self::PREFIX . 'countdown_title', 'Vi er snart klar'),
            'countdownMessage' => $this->settings->get(self::PREFIX . 'countdown_message', 'Noget nyt er på vej. Vi åbner snart igen.'),
            'countdownTarget' => $targetForInput,
            'maintenanceEnabled' => $this->boolean('maintenance_enabled'),
            'maintenanceTitle' => $this->settings->get(self::PREFIX . 'maintenance_title', 'Vi vedligeholder Nodexa'),
            'maintenanceMessage' => $this->settings->get(self::PREFIX . 'maintenance_message', 'Vi arbejder på platformen og er tilbage så hurtigt som muligt.'),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'countdown_enabled' => 'nullable|boolean',
            'countdown_title' => 'required|string|max:120',
            'countdown_message' => 'required|string|max:500',
            'countdown_target' => 'nullable|date_format:Y-m-d\\TH:i',
            'maintenance_enabled' => 'nullable|boolean',
            'maintenance_title' => 'required|string|max:120',
            'maintenance_message' => 'required|string|max:500',
        ]);

        $target = '';
        if (!empty($data['countdown_target'])) {
            $target = Carbon::createFromFormat(
                'Y-m-d\\TH:i',
                $data['countdown_target'],
                config('app.timezone')
            )->toIso8601String();
        }

        $values = [
            'countdown_enabled' => $request->boolean('countdown_enabled') ? '1' : '0',
            'countdown_title' => trim($data['countdown_title']),
            'countdown_message' => trim($data['countdown_message']),
            'countdown_target' => $target,
            'maintenance_enabled' => $request->boolean('maintenance_enabled') ? '1' : '0',
            'maintenance_title' => trim($data['maintenance_title']),
            'maintenance_message' => trim($data['maintenance_message']),
        ];

        foreach ($values as $key => $value) {
            $this->settings->set(self::PREFIX . $key, $value);
        }

        $this->alert->success('Countdown og maintenance-indstillinger er gemt.')->flash();

        return redirect()->route('admin.site-access');
    }

    private function boolean(string $key): bool
    {
        return filter_var(
            $this->settings->get(self::PREFIX . $key, '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
