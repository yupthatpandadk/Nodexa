<?php

namespace App\Http\Controllers;

use App\Models\StorefrontSite;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebEntryController extends Controller
{
    public function __invoke(Request $request)
    {
        $host = StorefrontSite::normalizeDomain($request->getHost());
        $panelHost = StorefrontSite::normalizeDomain((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));

        if ($host === $panelHost || $host === '') {
            return view('app');
        }

        $site = StorefrontSite::resolveDomain($host);

        // Backwards-compatible fallback for installs created before multisite.
        if (!$site) {
            $legacy = StorefrontSite::normalizeDomain((string) env('NODEXA_STOREFRONT_DOMAIN', ''));
            if ($legacy === '' && str_starts_with($panelHost, 'panel.')) {
                $legacy = substr($panelHost, 6);
            }
            if ($host === $legacy || $host === 'www.'.$legacy) {
                $site = StorefrontSite::query()->where('is_default', true)->where('enabled', true)->first();
            }
        }

        abort_unless($site && $site->enabled, 404);

        $path = trim($request->path(), '/');
        $allowed = ['', 'games', 'minecraft', 'fivem', 'vps', 'cart', 'checkout', 'faq'];
        abort_unless(in_array($path, $allowed, true), 404);

        $products = $site->products()->where('enabled', true)->get();

        return response()->view('storefront', [
            'site' => $site,
            'products' => $products,
            'storefrontPath' => $path,
        ]);
    }
}
