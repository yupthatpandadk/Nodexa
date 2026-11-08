<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\NodexaEventService;
use Pterodactyl\Services\Nodexa\OrderRemovalService;
use Pterodactyl\Services\Nodexa\WalletService;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $wallet,
        private NodexaEventService $events,
        private OrderRemovalService $removal,
    ) {
    }

    public function index(): View
    {
        return view('admin.wallet.index', [
            'wallets' => DB::table('nodexa_wallets as w')
                ->leftJoin('users as u', 'u.id', '=', 'w.user_id')
                ->select(['w.*', 'u.email', 'u.username'])
                ->orderByDesc('w.updated_at')->limit(100)->get(),
            'transactions' => DB::table('nodexa_wallet_transactions as t')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->select(['t.*', 'u.email'])
                ->orderByDesc('t.id')->limit(100)->get(),
        ]);
    }

    public function credit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => ['required', 'string', 'regex:/^[0-9]{1,6}(?:[.,][0-9]{1,2})?$/'],
            'reason' => 'required|string|min:5|max:500',
            'request_id' => 'required|uuid',
        ]);

        $amount = str_replace(',', '.', $data['amount']);
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '0');
        $cents = (int) $whole * 100 + (int) str_pad($decimal, 2, '0');

        try {
            $this->wallet->credit(
                (int) $data['user_id'],
                $cents,
                'admin_credit',
                'admin-credit-' . $data['request_id'],
                $data['reason'],
                (int) $request->user()->id
            );
        } catch (\Throwable $exception) {
            return back()->withInput()->withErrors(['wallet' => $exception->getMessage()]);
        }

        $this->events->audit(
            (int) $request->user()->id,
            'wallet',
            'credit.granted',
            'Wallet credit granted: ' . number_format($cents / 100, 2) . ' DKK',
            'user',
            (int) $data['user_id'],
            ['amount_cents' => $cents, 'request_id' => $data['request_id']],
            $request
        );

        return redirect()->route('admin.wallet')->with('success', 'Wallet-kredit er tilføjet.');
    }

    public function refundCheckout(Request $request, int $checkout): RedirectResponse
    {
        $request->validate([
            'confirmed_no_service' => 'accepted',
            'confirmed_customer_consent' => 'accepted',
        ]);

        try {
            $number = $this->removal->refundFailedCheckoutToWallet(
                $checkout,
                (int) $request->user()->id
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['wallet' => $exception->getMessage()]);
        }

        $this->events->audit(
            (int) $request->user()->id,
            'wallet',
            'checkout.credited',
            'Paid failed checkout converted to customer Wallet credit',
            'cart_checkout',
            $checkout,
            ['order_number' => $number, 'customer_consent_confirmed' => true],
            $request
        );

        return back()->with('success', 'Ordre ' . $number . ' er omdannet til Wallet-kredit. Der er ikke sendt en Stripe-refundering.');
    }
}
