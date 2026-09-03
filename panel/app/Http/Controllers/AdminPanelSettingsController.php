<?php

namespace App\Http\Controllers;

use App\Models\PanelSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminPanelSettingsController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403, 'Administrator permission required.');
    }

    public function show(Request $request)
    {
        $this->admin($request);

        return response()->json([
            'settings' => PanelSetting::values(),
            'locales' => [
                ['value' => 'da', 'label' => 'Dansk'],
                ['value' => 'en', 'label' => 'English'],
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request)
    {
        $this->admin($request);

        $data = $request->validate([
            'panel_name' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'panel_url' => ['required', 'url:http,https', 'max:255'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'locale' => ['required', Rule::in(['da', 'en'])],
            'support_email' => ['nullable', 'email', 'max:255'],
        ]);

        $data['panel_url'] = rtrim($data['panel_url'], '/');
        $data['company_name'] = trim((string) ($data['company_name'] ?? ''));
        $data['support_email'] = trim((string) ($data['support_email'] ?? ''));

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                PanelSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $value]
                );
            }
        });

        config([
            'app.name' => $data['panel_name'],
            'app.url' => $data['panel_url'],
            'app.timezone' => $data['timezone'],
            'app.locale' => $data['locale'],
        ]);
        date_default_timezone_set($data['timezone']);

        return response()->json([
            'message' => 'Kontrolpanel-indstillingerne er gemt.',
            'settings' => PanelSetting::values(),
        ]);
    }
}
