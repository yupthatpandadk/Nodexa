<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Services\Nodexa\NodexaCartService;
use Pterodactyl\Services\Nodexa\OrderRemovalService;
use Pterodactyl\Services\Nodexa\WalletService;
use Pterodactyl\Services\Nodexa\StripePaymentService;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    private const SESSION_KEY = 'nodexa_cart';
    private const COUPON_KEY = 'nodexa_cart_coupon';

    public function __construct(
        private NodexaCartService $cart,
        private StripePaymentService $stripe,
        private OrderRemovalService $removal,
        private WalletService $wallet,
    ) {
    }

    public function index(Request $request): View
    {
        $summary = $this->cart->resolve(
            (array) $request->session()->get(self::SESSION_KEY, []),
            $request->session()->get(self::COUPON_KEY)
        );

        if ($request->session()->has(self::COUPON_KEY) && !$summary['coupon']) {
            $request->session()->forget(self::COUPON_KEY);
        }

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
            ->with('success', \Pterodactyl\Services\Nodexa\CustomerBranding::label($plan->name) . ' er lagt i kurven.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40',
        ]);

        $cart = (array) $request->session()->get(self::SESSION_KEY, []);
        $base = $this->cart->resolve($cart);

        if ($base['count'] < 1) {
            return back()->withErrors(['coupon' => 'Tilføj mindst én vare før du bruger en rabatkode.']);
        }

        try {
            $coupon = $this->cart->validateCoupon($data['code'], $base['subtotal_cents']);
        } catch (\Throwable $exception) {
            return back()->withErrors(['coupon' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        $request->session()->put(self::COUPON_KEY, $coupon['code']);

        return back()->with('success', 'Rabatkode ' . $coupon['code'] . ' er anvendt.');
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $request->session()->forget(self::COUPON_KEY);

        return back()->with('success', 'Rabatkoden er fjernet.');
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
        $request->session()->forget([self::SESSION_KEY, self::COUPON_KEY]);

        return redirect()->route('store.cart')->with('success', 'Kurven er tømt.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $summary = $this->cart->resolve(
            (array) $request->session()->get(self::SESSION_KEY, []),
            $request->session()->get(self::COUPON_KEY)
        );

        if ($summary['count'] < 1) {
            return redirect()->route('store.cart')->withErrors(['cart' => 'Kurven er tom.']);
        }

        if ($request->session()->has(self::COUPON_KEY) && !$summary['coupon']) {
            $request->session()->forget(self::COUPON_KEY);

            return redirect()->route('store.cart.checkout')
                ->withErrors(['coupon' => 'Rabatkoden er ikke længere gyldig og er blevet fjernet.']);
        }

        return view('store.cart.checkout', [
            'summary' => $summary,
            'stripeEnabled' => $this->stripe->isEnabled(),
            'walletBalanceCents' => $this->wallet->balanceCents((int) $request->user()->id),
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $request->validate(['payment_method' => 'nullable|in:stripe,wallet']);
        $items = (array) $request->session()->get(self::SESSION_KEY, []);
        try {
            $preview = $this->cart->resolve($items, $request->session()->get(self::COUPON_KEY));
            $totalCents = (int) round(((float) ($preview['total'] ?? 0)) * 100);
            $walletCents = $this->wallet->balanceCents((int) $request->user()->id);
        } catch (\Throwable $exception) {
            return redirect()->route('store.cart.checkout')->withErrors(['wallet' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        // Wallet is always applied first. Use Stripe for the remaining balance.
        $paymentMethod = $totalCents > 0 && $walletCents >= $totalCents ? 'wallet' : 'stripe';

        try {
            $checkoutId = $this->cart->createCheckout(
                $request->user()->id,
                $items,
                $request->session()->get(self::COUPON_KEY)
            );
        } catch (\Throwable $exception) {
            return redirect()->route('store.cart')->withErrors(['cart' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        $request->session()->forget([self::SESSION_KEY, self::COUPON_KEY]);

        if ($paymentMethod === 'wallet') {
            return $this->payWallet($request, $checkoutId);
        }

        if ($this->stripe->isEnabled()) {
            try {
                $session = $this->stripe->createCheckoutSession($checkoutId, $request->user()->id);

                return redirect()->away($session['url']);
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()
                    ->route('store.cart.order', $checkoutId)
                    ->withErrors(['stripe' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
            }
        }

        return redirect()
            ->route('store.cart.order', $checkoutId)
            ->with('success', 'Din samlede ordre er oprettet.');
    }

    public function payWallet(Request $request, int $checkout): RedirectResponse
    {
        try {
            $result = $this->wallet->payCheckout($checkout, (int) $request->user()->id);
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('store.cart.order', $checkout)
                ->withErrors(['wallet' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        $message = !empty($result['completed'])
            ? 'Wallet-betalingen er gennemført, og alle services er behandlet.'
            : 'Wallet-betalingen er registreret. En eller flere services kræver opfølgning fra admin.';

        return redirect()->route('store.cart.order', $checkout)
            ->with(!empty($result['completed']) ? 'success' : 'warning', $message);
    }

    public function destroy(Request $request, int $checkout): RedirectResponse
    {
        try {
            $number = $this->removal->removeCheckout($checkout, (int) $request->user()->id);
        } catch (\Throwable $exception) {
            return back()->withErrors(['order' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        return redirect()
            ->route('store.client.billing')
            ->with('success', 'Den ubetalte ordre ' . $number . ' er annulleret og fjernet fra oversigten.');
    }

    public function order(Request $request, int $checkout): View
    {
        $row = DB::table('nodexa_cart_checkouts')
            ->where('id', $checkout)
            ->where('user_id', $request->user()->id)
            ->where('hidden_from_client', false)
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
            'stripeEnabled' => $this->stripe->isEnabled(),
            'walletBalanceCents' => $this->wallet->balanceCents((int) $request->user()->id),
        ]);
    }
}
