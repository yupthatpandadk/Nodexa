<?php

namespace App\Http\Controllers;

use App\Models\StorefrontProduct;
use App\Models\StorefrontSite;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StorefrontSiteController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403, 'Administrator permission required.');
    }

    public function index(Request $request)
    {
        $this->admin($request);
        return StorefrontSite::query()->withCount('products')->with(['products' => fn ($q) => $q->orderBy('sort_order')])->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $this->siteData($request);
        $this->assertDomainsAvailable($data['primary_domain'], $data['aliases']);

        return DB::transaction(function () use ($data) {
            if (($data['is_default'] ?? false) === true) StorefrontSite::query()->update(['is_default' => false]);
            $site = StorefrontSite::create($data);
            if (!StorefrontSite::query()->where('is_default', true)->exists()) $site->update(['is_default' => true]);
            return response()->json($site->fresh()->load('products'), 201);
        });
    }

    public function update(Request $request, StorefrontSite $site)
    {
        $this->admin($request);
        $data = $this->siteData($request, $site);
        $this->assertDomainsAvailable($data['primary_domain'], $data['aliases'], $site->id);

        return DB::transaction(function () use ($site, $data) {
            if (($data['is_default'] ?? false) === true) StorefrontSite::query()->whereKeyNot($site->id)->update(['is_default' => false]);
            $site->update($data);
            if (!$site->is_default && !StorefrontSite::query()->where('is_default', true)->exists()) $site->update(['is_default' => true]);
            return $site->fresh()->load('products');
        });
    }

    public function destroy(Request $request, StorefrontSite $site)
    {
        $this->admin($request);
        abort_if(StorefrontSite::query()->count() <= 1, 409, 'At least one storefront must remain.');
        $wasDefault = $site->is_default;
        $site->delete();
        if ($wasDefault) StorefrontSite::query()->orderBy('id')->first()?->update(['is_default' => true]);
        return response()->noContent();
    }

    public function storeProduct(Request $request, StorefrontSite $site)
    {
        $this->admin($request);
        $data = $this->productData($request, $site);
        $data['storefront_site_id'] = $site->id;
        return response()->json(StorefrontProduct::create($data), 201);
    }

    public function updateProduct(Request $request, StorefrontSite $site, StorefrontProduct $product)
    {
        $this->admin($request);
        abort_unless($product->storefront_site_id === $site->id, 404);
        $product->update($this->productData($request, $site, $product));
        return $product->fresh();
    }

    public function destroyProduct(Request $request, StorefrontSite $site, StorefrontProduct $product)
    {
        $this->admin($request);
        abort_unless($product->storefront_site_id === $site->id, 404);
        $product->delete();
        return response()->noContent();
    }

    private function siteData(Request $request, ?StorefrontSite $site = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => ['required','string','max:120','regex:/^[a-z0-9-]+$/', Rule::unique('storefront_sites','slug')->ignore($site?->id)],
            'primary_domain' => 'required|string|max:255',
            'aliases' => 'nullable|array|max:20',
            'aliases.*' => 'string|max:255',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'logo_url' => 'nullable|string|max:2048',
            'primary_color' => ['required','string','max:20','regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required','string','max:20','regex:/^#[0-9a-fA-F]{6}$/'],
            'currency' => 'required|string|size:3',
            'locale' => 'required|string|max:12',
            'title' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:3000',
            'support_email' => 'nullable|email|max:255',
            'panel_url' => 'nullable|url|max:2048',
            'settings' => 'nullable|array',
        ]);

        $data['primary_domain'] = StorefrontSite::normalizeDomain($data['primary_domain']);
        abort_if($data['primary_domain'] === '', 422, 'Invalid primary domain.');
        $data['aliases'] = array_values(array_unique(array_filter(array_map([StorefrontSite::class, 'normalizeDomain'], $data['aliases'] ?? []))));
        $data['aliases'] = array_values(array_diff($data['aliases'], [$data['primary_domain']]));
        $data['currency'] = strtoupper($data['currency']);
        return $data;
    }

    private function productData(Request $request, StorefrontSite $site, ?StorefrontProduct $product = null): array
    {
        return $request->validate([
            'slug' => ['required','string','max:120','regex:/^[a-z0-9-]+$/', Rule::unique('storefront_products','slug')->where(fn ($q) => $q->where('storefront_site_id', $site->id))->ignore($product?->id)],
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:3000',
            'price_cents' => 'required|integer|min:0',
            'billing_period' => 'required|string|max:30',
            'type' => 'required|string|max:40',
            'features' => 'nullable|array|max:30',
            'features.*' => 'string|max:255',
            'enabled' => 'boolean',
            'sort_order' => 'integer|min:0|max:999999',
            'settings' => 'nullable|array',
        ]);
    }

    private function assertDomainsAvailable(string $primary, array $aliases, ?int $ignoreId = null): void
    {
        $wanted = array_values(array_unique(array_merge([$primary], $aliases)));
        $sites = StorefrontSite::query()->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->get(['id','primary_domain','aliases']);
        foreach ($sites as $existing) {
            $domains = array_map([StorefrontSite::class, 'normalizeDomain'], array_merge([$existing->primary_domain], $existing->aliases ?? []));
            foreach ($wanted as $domain) abort_if(in_array($domain, $domains, true), 422, "Domain {$domain} is already assigned to another storefront.");
        }
    }
}
