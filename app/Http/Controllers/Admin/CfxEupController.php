<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Nodexa\CfxEupService;
use Pterodactyl\Services\Nodexa\NodexaEventService;

class CfxEupController extends Controller
{
    public function __construct(
        private CfxEupService $eup,
        private NodexaEventService $events,
    ) {
    }

    public function index(): View
    {
        $keys = DB::table('nodexa_cfx_eup_keys as k')
            ->leftJoin('users as u', 'u.id', '=', 'k.assigned_user_id')
            ->select('k.*', 'u.email as assigned_email')
            ->orderByDesc('k.id')
            ->get();

        $orders = DB::table('nodexa_cfx_eup_orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('nodexa_invoices as i', 'i.id', '=', 'o.first_invoice_id')
            ->select('o.*', 'u.email as user_email', 'i.number as invoice_number', 'i.status as invoice_status')
            ->orderByDesc('o.id')
            ->limit(200)
            ->get();

        $users = User::query()->orderBy('email')->get(['id', 'email', 'username', 'name_first', 'name_last']);
        $availableKeys = $keys->where('status', 'available');

        return view('admin.cfx-eup.index', compact('keys', 'orders', 'users', 'availableKeys'));
    }

    public function storeKey(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:5000',
            'label' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $id = $this->eup->addKey($data['key'], $data['label'] ?? null, $data['notes'] ?? null, $request->user()->id);
        } catch (\Throwable $exception) {
            return back()->withErrors(['key' => $exception->getMessage()]);
        }

        $this->events->audit($request->user()->id, 'cfx_eup', 'key.created', $data['label'] ?? 'CFX EUP key', 'cfx_eup_key', $id, [], $request);

        return back()->with('success', 'CFX EUP key er tilføjet sikkert til key-poolen.');
    }

    public function createOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'eup_key_id' => 'nullable|integer|exists:nodexa_cfx_eup_keys,id',
            'monthly_price' => 'required|numeric|min:0.01|max:999999.99',
            'currency' => 'required|string|size:3',
            'description' => 'required|string|max:180',
        ]);

        try {
            $result = $this->eup->createRecurringOrder(
                (int) $data['user_id'],
                (float) $data['monthly_price'],
                strtoupper($data['currency']),
                trim($data['description']),
                isset($data['eup_key_id']) ? (int) $data['eup_key_id'] : null,
                $request->user()->id
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        $this->events->audit($request->user()->id, 'cfx_eup', 'order.created', 'Recurring CFX EUP order', 'cfx_eup_order', $result['orderId'], $result, $request);

        return back()->with('success', 'EUP ordre og første faktura er oprettet. Keyen bliver frigivet til kunden ved betaling.');
    }

    public function assignDirect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'eup_key_id' => 'required|integer|exists:nodexa_cfx_eup_keys,id',
            'description' => 'required|string|max:180',
        ]);

        try {
            $orderId = $this->eup->assignDirect(
                (int) $data['user_id'],
                (int) $data['eup_key_id'],
                trim($data['description']),
                $request->user()->id
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['assign' => $exception->getMessage()]);
        }

        $this->events->audit($request->user()->id, 'cfx_eup', 'key.assigned', $data['description'], 'cfx_eup_order', $orderId, [], $request);

        return back()->with('success', 'EUP key er givet direkte til kunden uden månedlig betaling.');
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        try {
            $this->eup->cancelOrder($order);
        } catch (\Throwable $exception) {
            return back()->withErrors(['cancel' => $exception->getMessage()]);
        }

        $this->events->audit($request->user()->id, 'cfx_eup', 'order.cancelled', null, 'cfx_eup_order', $order, [], $request);

        return back()->with('success', 'EUP ordren er annulleret, og keyen er frigivet til poolen.');
    }
}
