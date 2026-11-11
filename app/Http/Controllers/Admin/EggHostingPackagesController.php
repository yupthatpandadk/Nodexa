<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Services\Nodexa\EggHostingPackageService;
use Pterodactyl\Services\Nodexa\MinecraftHostingPackageService;
use RuntimeException;

class EggHostingPackagesController extends Controller
{
    public function index(EggHostingPackageService $packages, MinecraftHostingPackageService $minecraftPackages): View
    {
        $eggs = Egg::with('nest')->orderBy('name')->get();
        $minecraftEggs = $eggs->filter(
            fn (Egg $egg) => $minecraftPackages->supportsEgg($egg)
        );

        return view('admin.store.egg-packages', [
            'minecraftEggs' => $minecraftEggs,
            'minecraftProfiles' => $minecraftPackages->profiles(),
            'eggs' => $eggs,
            'categories' => EggHostingPackageService::GAME_LABELS,
            'tiers' => EggHostingPackageService::TIERS,
            'profiles' => $packages->allProfiles(),
            'products' => StoreProduct::orderBy('name')->get(),
        ]);
    }

    public function generate(Request $request, EggHostingPackageService $packages): RedirectResponse
    {
        $data = $request->validate([
            'egg_id' => 'required|integer|exists:eggs,id',
            'category' => 'required|in:auto,minecraft,fivem,rust,ark,voice,generic',
            'tiers' => 'required|array|min:1|max:4',
            'tiers.*' => 'required|in:starter,plus,pro,ultimate|distinct',
            'prices' => 'nullable|array',
            'prices.starter' => 'nullable|numeric|min:0.01|max:99999',
            'prices.plus' => 'nullable|numeric|min:0.01|max:99999',
            'prices.pro' => 'nullable|numeric|min:0.01|max:99999',
            'prices.ultimate' => 'nullable|numeric|min:0.01|max:99999',
        ]);

        $egg = Egg::with('nest')->findOrFail($data['egg_id']);

        try {
            $result = $packages->generate(
                $egg,
                $data['category'],
                $data['tiers'],
                $data['prices'] ?? []
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('admin.store.egg-packages')
                ->withInput()->withErrors(['packages' => $exception->getMessage()]);
        }

        return redirect()->route('admin.store.egg-packages')
            ->with('success', $result['created'] . ' skjulte pakker oprettet. '
                . $result['skipped'] . ' eksisterende pakker blev sprunget over. '
                . 'Gennemgå priser og ressourcer, før pakkerne aktiveres.');
    }

    /**
     * Generate named Minecraft plans as unpublished products.
     * Safe to retry: the service inserts stable, Egg-specific slugs only once.
     */
    public function generateMinecraft(Request $request, MinecraftHostingPackageService $packages): RedirectResponse
    {
        $data = $request->validate([
            'egg_id' => ['required', 'integer', 'exists:eggs,id'],
            'tiers' => ['required', 'array', 'min:1', 'max:8'],
            'tiers.*' => ['required', 'string', 'in:dirt,grass,stone,iron,gold,diamond,emerald,obsidian', 'distinct'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
        ]);

        $egg = Egg::with('nest')->findOrFail($data['egg_id']);

        try {
            $result = $packages->generate($egg, $data['tiers'], $data['prices'] ?? []);
        } catch (RuntimeException $exception) {
            return redirect()->route('admin.store.egg-packages')
                ->withInput()->withErrors(['minecraft' => $exception->getMessage()]);
        }

        return redirect()->route('admin.store.egg-packages')
            ->with('success', $result['created'] . ' Minecraft-pakker oprettet som skjulte produkter. '
                . $result['skipped'] . ' eksisterende pakker blev sprunget over. '
                . 'Gennemgå priser, CPU, RAM og nodekapacitet, før du aktiverer dem.');
    }

    public function updateDetails(Request $request, StoreProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'game' => 'required|string|max:80',
            'description' => 'nullable|string|max:2000',
            'price_monthly' => 'required|numeric|min:0|max:99999',
            'memory' => 'required|integer|min:128|max:1048576',
            'disk' => 'required|integer|min:256|max:10485760',
            'cpu' => 'required|integer|min:10|max:6400',
            'databases' => 'required|integer|min:0|max:100',
            'backups' => 'required|integer|min:0|max:100',
            'allocations' => 'required|integer|min:1|max:100',
        ]);

        $product->update($data);

        return redirect()->route('admin.store.egg-packages')
            ->with('success', 'Pakken er opdateret til fremtidige bestillinger. '
                . 'Eksisterende servere og abonnementer er ikke ændret.');
    }
}
