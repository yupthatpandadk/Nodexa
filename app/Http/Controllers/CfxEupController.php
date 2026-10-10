<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pterodactyl\Services\Nodexa\CfxEupService;

class CfxEupController extends Controller
{
    public function __construct(private CfxEupService $eup)
    {
    }

    public function index(Request $request): View
    {
        $orders = DB::table('nodexa_cfx_eup_orders as o')
            ->leftJoin('nodexa_cfx_eup_keys as k', 'k.id', '=', 'o.eup_key_id')
            ->leftJoin('nodexa_subscriptions as s', 's.id', '=', 'o.subscription_id')
            ->leftJoin('nodexa_invoices as i', 'i.id', '=', 'o.first_invoice_id')
            ->where('o.user_id', $request->user()->id)
            ->select([
                'o.*',
                'k.key_encrypted',
                'k.label as key_label',
                'k.status as key_status',
                's.next_invoice_at',
                's.status as subscription_status',
                'i.number as first_invoice_number',
                'i.status as first_invoice_status',
            ])
            ->orderByDesc('o.id')
            ->get()
            ->map(function ($order) {
                $order->plain_key = null;

                if ($order->status === 'active' && $order->key_status === 'assigned' && $order->key_encrypted) {
                    try {
                        $order->plain_key = $this->eup->decryptKey($order->key_encrypted);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }

                unset($order->key_encrypted);

                return $order;
            });

        return view('store.client.eup-keys', compact('orders'));
    }
}
