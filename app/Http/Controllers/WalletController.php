<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Services\Nodexa\WalletService;

class WalletController extends Controller
{
    public function index(Request $request, WalletService $wallet): View
    {
        $userId = (int) $request->user()->id;

        return view('store.client.wallet', [
            'balanceCents' => $wallet->balanceCents($userId),
            'transactions' => $wallet->transactions($userId),
        ]);
    }
}
