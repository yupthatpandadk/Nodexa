<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\ThemeService;

class ThemesController extends Controller
{
    public function __construct(
        private ThemeService $themes,
        private AlertsMessageBag $alert,
    ) {
    }

    public function index(): View
    {
        return view('admin.settings.themes', [
            'presets' => ThemeService::presets(),
            'themeState' => $this->themes->current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'theme' => ['required', 'string', Rule::in(array_keys(ThemeService::presets()))],
            'automatic' => ['nullable', 'boolean'],
            'snowfall' => ['nullable', 'boolean'],
        ]);

        $this->themes->save($values['theme'], $request->boolean('automatic'), $request->boolean('snowfall'));
        $this->alert->success('Temaet er gemt og er nu aktivt på Nodexa.')->flash();

        return redirect()->route('admin.settings.themes');
    }
}
