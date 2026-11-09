<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Pterodactyl\Services\Nodexa\StripePaymentService;

class StripePaymentController extends Controller
{
    public function pay(Request $request, int $checkout, StripePaymentService $stripe): RedirectResponse
    {
        try {
            $session = $stripe->createCheckoutSession($checkout, $request->user()->id);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['stripe' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        return redirect()->away($session['url']);
    }

    public function cancel(Request $request, int $checkout, StripePaymentService $stripe): RedirectResponse
    {
        try {
            $stripe->cancelUnpaidCheckout($checkout, (int) $request->user()->id);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['stripe' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        return redirect()->route('store.cart.order', $checkout)
            ->with('success', 'Stripe-betalingen er annulleret. Reserveret Wallet-kredit er frigivet.');
    }

    public function success(Request $request, int $checkout, StripePaymentService $stripe): RedirectResponse
    {
        $sessionId = trim((string) $request->query('session_id'));

        if ($sessionId === '') {
            return redirect()
                ->route('store.cart.order', $checkout)
                ->withErrors(['stripe' => 'Stripe returnerede ikke et Checkout Session ID.']);
        }

        try {
            $result = $stripe->confirmCheckoutSession($checkout, $request->user()->id, $sessionId);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('store.cart.order', $checkout)
                ->withErrors(['stripe' => \Pterodactyl\Services\Nodexa\CustomerBranding::errorMessage($exception)]);
        }

        if (!$result['paid']) {
            return redirect()
                ->route('store.cart.order', $checkout)
                ->with('warning', $result['message']);
        }

        return redirect()
            ->route('store.cart.order', $checkout)
            ->with('success', 'Betalingen er bekræftet af Stripe. Dine services bliver aktiveret.');
    }
}
