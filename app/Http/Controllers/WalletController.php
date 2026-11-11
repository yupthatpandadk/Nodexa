<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Services\Nodexa\WalletService;
use Pterodactyl\Services\Nodexa\WalletUsagePolicy;

class WalletController extends Controller
{
    public function index(Request $request, WalletService $wallet, WalletUsagePolicy $policy): View
    {
        $userId = (int) $request->user()->id;

        return view('store.client.wallet', [
            'balanceCents' => $wallet->balanceCents($userId),
            'walletAllowsVps' => $policy->allowsVps(),
            'transactions' => $wallet->transactions($userId),
        ]);
    }
}
