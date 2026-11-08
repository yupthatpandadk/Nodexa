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
            'user_id' => 'nullable|integer|exists:users,id|required_without:email',
            'email' => 'nullable|email|max:255',
            'amount' => ['required', 'string', 'regex:/^[0-9]{1,6}(?:[.,][0-9]{1,2})?$/'],
            'reason' => 'required|string|min:5|max:500',
            'request_id' => 'required|uuid',
        ]);

        $userId = (int) ($data['user_id'] ?? 0);
        if (!empty($data['email'])) {
            $emailUserId = (int) (DB::table('users')->where('email', $data['email'])->value('id') ?? 0);
            if ($emailUserId < 1) {
                return back()->withInput()->withErrors(['wallet' => 'Kunden med den angivne e-mail blev ikke fundet.']);
            }
            if ($userId > 0 && $userId !== $emailUserId) {
                return back()->withInput()->withErrors(['wallet' => 'Kunde-ID og e-mail tilhører ikke den samme konto.']);
            }
            $userId = $emailUserId;
        }

        $amount = str_replace(',', '.', $data['amount']);
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '0');
        $cents = (int) $whole * 100 + (int) str_pad($decimal, 2, '0');

        try {
            $this->wallet->credit(
                $userId,
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
            $userId,
            ['amount_cents' => $cents, 'request_id' => $data['request_id']],
            $request
        );

        $this->events->notify(
            $userId,
            'Kredit tilføjet til Wallet',
            number_format($cents / 100, 2, ',', '.') . ' DKK er tilføjet til din Nodexa Wallet.',
            'billing',
            '/client/wallet'
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

        $checkoutRow = DB::table('nodexa_cart_checkouts')->where('id', $checkout)->first();
        if ($checkoutRow) {
            $this->events->notify(
                (int) $checkoutRow->user_id,
                'Refundering til Wallet',
                number_format((float) $checkoutRow->total, 2, ',', '.') . ' DKK fra ordre ' . $number . ' er blevet krediteret din Wallet.',
                'billing',
                '/client/wallet'
            );
        }

        return back()->with('success', 'Ordre ' . $number . ' er omdannet til Wallet-kredit. Der er ikke sendt en Stripe-refundering.');
    }
}
