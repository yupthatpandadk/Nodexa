<?php

namespace App\Http\Controllers;

use App\Models\StorefrontProduct;
use App\Models\StorefrontSite;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WebEntryController extends Controller
{
    public function __invoke(Request $request)
    {
        $host = StorefrontSite::normalizeDomain($request->getHost());
        $panelHost = StorefrontSite::normalizeDomain((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));

        if ($host === $panelHost || $host === '') {
            return view('app');
        }

        $legacy = StorefrontSite::normalizeDomain((string) env('NODEXA_STOREFRONT_DOMAIN', ''));
        if ($legacy === '' && str_starts_with($panelHost, 'panel.')) {
            $legacy = substr($panelHost, 6);
        }

        $site = null;
        $products = collect();

        // A storefront must never take the whole public website down merely
        // because an update reached PHP before its multisite migration/cache.
        // Resolve from the database when available and gracefully fall back to
        // the legacy storefront until the updater has completed migrations.
        try {
            if (Schema::hasTable('storefront_sites')) {
                $site = StorefrontSite::resolveDomain($host);

                if (!$site && ($host === $legacy || $host === 'www.'.$legacy)) {
                    $site = StorefrontSite::query()
                        ->where('is_default', true)
                        ->where('enabled', true)
                        ->first();
                }

                if ($site && Schema::hasTable('storefront_products')) {
                    $products = $site->products()->where('enabled', true)->get();
                }
            }
        } catch (Throwable) {
            // Continue into the safe legacy fallback below. The Admin → Fejl
            // scanner can still surface database/runtime health independently.
            $site = null;
            $products = collect();
        }

        if (!$site && ($host === $legacy || $host === 'www.'.$legacy)) {
            [$site, $products] = $this->legacyStorefront($legacy ?: $host);
        }

        abort_unless($site && $site->enabled, 404);

        $path = trim($request->path(), '/');
        $allowed = ['', 'games', 'minecraft', 'fivem', 'vps', 'cart', 'checkout', 'faq'];
        abort_unless(in_array($path, $allowed, true), 404);

        $panelUrl = $site->panel_url ?: config('app.url');
        $name = trim((string) ($site->name ?: 'Nodexa Hosting'));
        $initial = strtoupper(substr($name, 0, 1) ?: 'N');

        // Precompute JSON here rather than putting closures/arrow functions
        // inside Blade @json directives. This keeps compiled views simple and
        // avoids Blade parser/runtime failures on production installs.
        $sitePayload = [
            'id' => $site->id ?: 0,
            'slug' => $site->slug ?: 'default',
            'name' => $name,
            'currency' => $site->currency ?: 'DKK',
            'locale' => $site->locale ?: 'da-DK',
            'tagline' => $site->tagline,
            'description' => $site->description,
            'support_email' => $site->support_email,
        ];

        $productPayload = $products->map(static fn ($p) => [
            'id' => $p->id ?: 0,
            'slug' => $p->slug,
            'name' => $p->name,
            'description' => $p->description,
            'price_cents' => (int) $p->price_cents,
            'billing_period' => $p->billing_period,
            'type' => $p->type,
            'features' => is_array($p->features) ? $p->features : [],
        ])->values()->all();

        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return response()->view('storefront', [
            'site' => $site,
            'products' => $products,
            'storefrontPath' => $path,
            'panelUrl' => $panelUrl,
            'initial' => $initial,
            'siteJson' => json_encode($sitePayload, $jsonFlags) ?: '{}',
            'productsJson' => json_encode($productPayload, $jsonFlags) ?: '[]',
        ]);
    }

    /** @return array{0: StorefrontSite, 1: Collection<int, StorefrontProduct>} */
    private function legacyStorefront(string $domain): array
    {
        $site = new StorefrontSite([
            'name' => (string) (env('NODEXA_STOREFRONT_NAME') ?: 'Revive Gaming'),
            'slug' => 'legacy',
            'primary_domain' => $domain,
            'aliases' => ['www.'.$domain],
            'enabled' => true,
            'is_default' => true,
            'primary_color' => '#745cff',
            'accent_color' => '#9a6dff',
            'currency' => 'DKK',
            'locale' => 'da-DK',
            'title' => 'Revive Gaming · Game Server Hosting',
            'tagline' => 'Hurtig hosting. Ét samlet kontrolpanel.',
            'description' => 'Game server hosting med hurtig provisionering, backups og Nodexa kontrolpanel.',
            'panel_url' => (string) config('app.url'),
        ]);

        $products = collect([
            new StorefrontProduct(['slug'=>'fivem','name'=>'FiveM Performance','description'=>'Optimeret FiveM hosting med NVMe og hurtig CPU.','price_cents'=>12900,'billing_period'=>'monthly','type'=>'game','features'=>['4 vCPU high-frequency','8 GB RAM','50 GB NVMe','DDoS-beskyttelse','Nodexa Game Panel'],'enabled'=>true]),
            new StorefrontProduct(['slug'=>'minecraft','name'=>'Minecraft Pro','description'=>'Minecraft hosting til vanilla, plugins og modpacks.','price_cents'=>7900,'billing_period'=>'monthly','type'=>'game','features'=>['3 vCPU','6 GB RAM','35 GB NVMe','Automatiske backups','Nodexa Game Panel'],'enabled'=>true]),
            new StorefrontProduct(['slug'=>'vps','name'=>'Cloud VPS','description'=>'Fleksibel Linux VPS til bots, websites og services.','price_cents'=>9900,'billing_period'=>'monthly','type'=>'vps','features'=>['4 vCPU','8 GB RAM','80 GB NVMe','1 Gbit netværk','Root adgang'],'enabled'=>true]),
        ]);

        return [$site, $products];
    }
}
