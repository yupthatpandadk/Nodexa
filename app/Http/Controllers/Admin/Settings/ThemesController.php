<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\ThemeService;
use Pterodactyl\Services\Nodexa\FrontendThemeService;

class ThemesController extends Controller
{
    public function __construct(
        private ThemeService $themes,
        private FrontendThemeService $frontendThemes,
        private AlertsMessageBag $alert,
    ) {
    }

    public function index(): View
    {
        return view('admin.settings.themes', [
            'presets' => ThemeService::presets(),
            'themeState' => $this->themes->current(),
            'frontendPresets' => FrontendThemeService::presets(),
            'frontendSelected' => $this->frontendThemes->current(),
        ]);
    }

    /**
     * Storefront layout selection is independent from global palettes/snow.
     */
    public function updateFrontend(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'frontend_theme' => ['required', 'string', Rule::in(array_keys(FrontendThemeService::presets()))],
        ]);

        $this->frontendThemes->save($values['frontend_theme']);
        $this->alert->success('Frontend-layoutet er gemt. Admin Area og kontrolpanelets design er uændret.')->flash();

        return redirect()->route('admin.settings.themes');
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
