<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Services\Nodexa\NodexaCartService;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    private const SESSION_KEY = 'nodexa_cart';

    public function __construct(private NodexaCartService $cart)
    {
    }

    public function index(Request $request): View
    {
        $summary = $this->cart->resolve((array) $request->session()->get(self::SESSION_KEY, []));

        return view('store.cart.index', compact('summary'));
    }

    public function addServer(Request $request, StoreProduct $product): RedirectResponse
    {
        abort_unless($product->enabled, 404);

        $data = $request->validate([
            'server_name' => 'required|string|min:3|max:100',
        ]);

        $items = (array) $request->session()->get(self::SESSION_KEY, []);
        $key = (string) Str::uuid();

        $items[$key] = [
            'type' => 'game_server',
            'product_id' => $product->id,
            'configuration' => [
                'server_name' => trim($data['server_name']),
            ],
        ];

        $request->session()->put(self::SESSION_KEY, $items);

        return redirect()
            ->route('store.cart')
            ->with('success', $product->name . ' er lagt i kurven.');
    }

    public function addVps(Request $request, string $product): RedirectResponse
    {
        $plan = DB::table('nodexa_flax_products')
            ->where('slug', $product)
            ->where('enabled', true)
            ->first();

        abort_unless($plan, 404);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'min:3', 'max:190', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]*[A-Za-z0-9]$/'],
            'os_template_id' => 'required|integer|exists:nodexa_flax_os_templates,upstream_id',
        ]);

        $items = (array) $request->session()->get(self::SESSION_KEY, []);
        $key = (string) Str::uuid();

        $items[$key] = [
            'type' => 'flax_vps',
            'product_id' => $plan->id,
            'configuration' => [
                'hostname' => trim($data['hostname']),
                'os_template_id' => (int) $data['os_template_id'],
            ],
        ];

        $request->session()->put(self::SESSION_KEY, $items);

        return redirect()
            ->route('store.cart')
            ->with('success', $plan->name . ' er lagt i kurven.');
    }

    public function remove(Request $request, string $item): RedirectResponse
    {
        $items = (array) $request->session()->get(self::SESSION_KEY, []);

        if (array_key_exists($item, $items)) {
            unset($items[$item]);
            $request->session()->put(self::SESSION_KEY, $items);
        }

        return back()->with('success', 'Varen er fjernet fra kurven.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('store.cart')->with('success', 'Kurven er tømt.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $summary = $this->cart->resolve((array) $request->session()->get(self::SESSION_KEY, []));

        if ($summary['count'] < 1) {
            return redirect()->route('store.cart')->withErrors(['cart' => 'Kurven er tom.']);
        }

        return view('store.cart.checkout', compact('summary'));
    }

    public function place(Request $request): RedirectResponse
    {
        $items = (array) $request->session()->get(self::SESSION_KEY, []);

        try {
            $checkoutId = $this->cart->createCheckout($request->user()->id, $items);
        } catch (\Throwable $exception) {
            return redirect()->route('store.cart')->withErrors(['cart' => $exception->getMessage()]);
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('store.cart.order', $checkoutId)
            ->with('success', 'Din samlede ordre er oprettet.');
    }

    public function order(Request $request, int $checkout): View
    {
        $row = DB::table('nodexa_cart_checkouts')
            ->where('id', $checkout)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($row, 404);

        $items = DB::table('nodexa_cart_checkout_items')
            ->where('checkout_id', $checkout)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $item->configuration_data = json_decode((string) $item->configuration, true) ?: [];
        }

        return view('store.cart.order', [
            'checkout' => $row,
            'items' => $items,
        ]);
    }
}
