<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StorefrontCustomerController extends Controller
{
    public function dashboard(Request $request): View
    {
        return $this->render($request, 'dashboard');
    }

    public function services(Request $request): View
    {
        return $this->render($request, 'services');
    }

    public function invoices(Request $request): View
    {
        return $this->render($request, 'invoices');
    }

    public function support(Request $request): View
    {
        return $this->render($request, 'support');
    }

    public function account(Request $request): View
    {
        return $this->render($request, 'account');
    }

    private function render(Request $request, string $section): View
    {
        $user = $request->user();

        $services = DB::table('servers')
            ->where('owner_id', $user->id)
            ->orderBy('name')
            ->get([
                'id', 'uuid', 'uuidShort', 'name', 'description', 'suspended', 'installed',
                'memory', 'disk', 'cpu', 'created_at',
            ]);

        $tickets = collect();
        if (Schema::hasTable('nodexa_tickets')) {
            $tickets = DB::table('nodexa_tickets')
                ->where('user_id', $user->id)
                ->orderByRaw("FIELD(status, 'customer_reply', 'open', 'answered', 'closed')")
                ->orderByDesc('last_reply_at')
                ->limit(25)
                ->get();
        }

        $invoices = collect();
        if (Schema::hasTable('nodexa_invoices')) {
            $invoices = DB::table('nodexa_invoices')
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $stats = [
            'services' => $services->count(),
            'active_services' => $services->where('suspended', 0)->count(),
            'open_tickets' => $tickets->whereIn('status', ['open', 'answered', 'customer_reply'])->count(),
            'unpaid_invoices' => $invoices->whereIn('status', ['unpaid', 'overdue'])->count(),
        ];

        return view('storefront.customer', compact('user', 'services', 'tickets', 'invoices', 'stats', 'section'));
    }
}
