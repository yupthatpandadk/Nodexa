<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\SoftwareVersionService;

class BaseController extends Controller
{
    public function __construct(private SoftwareVersionService $version) {}

    public function index(): View
    {
        $has = static fn (string $table): bool => Schema::hasTable($table);
        $metrics = [
            'customers' => $has('users') ? DB::table('users')->where('root_admin', false)->count() : 0,
            'servers' => $has('servers') ? DB::table('servers')->count() : 0,
            'active_servers' => $has('servers') ? DB::table('servers')->where(function ($q) { $q->whereNull('status')->orWhereNotIn('status', ['suspended', 'install_failed', 'reinstall_failed']); })->count() : 0,
            'nodes' => $has('nodes') ? DB::table('nodes')->count() : 0,
            'pending_orders' => $has('billing_orders') ? DB::table('billing_orders')->whereIn('status', ['pending', 'paid', 'processing'])->count() : 0,
            'open_tickets' => $has('tickets') ? DB::table('tickets')->whereNotIn('status', ['closed', 'resolved'])->count() : 0,
            'unpaid' => $has('invoices') ? (float) DB::table('invoices')->where('status', 'unpaid')->sum('balance') : 0,
            'overdue' => $has('invoices') ? (float) DB::table('invoices')->where('status', 'unpaid')->whereNotNull('due_at')->where('due_at', '<', now())->sum('balance') : 0,
            'revenue_month' => $has('invoices') ? (float) DB::table('invoices')->where('status', 'paid')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total') : 0,
            'mrr' => $has('billing_orders') && $has('billing_products') ? (float) DB::table('billing_orders as o')->join('billing_products as p', 'p.id', '=', 'o.product_id')->whereIn('o.status', ['paid', 'processing', 'completed'])->where('p.billing_cycle', 'monthly')->sum('p.price') : 0,
        ];
        $recentOrders = $has('billing_orders') ? DB::table('billing_orders as o')->leftJoin('users as u', 'u.id', '=', 'o.user_id')->leftJoin('billing_products as p', 'p.id', '=', 'o.product_id')->select('o.id', 'o.status', 'o.created_at', 'u.username', 'p.name as product_name')->orderByDesc('o.id')->limit(6)->get() : collect();
        $alerts = collect();
        if ($metrics['overdue'] > 0) $alerts->push(['type' => 'warning', 'icon' => 'fa-exclamation-triangle', 'text' => 'Der er forfaldne fakturaer, som kræver opmærksomhed.']);
        if ($metrics['pending_orders'] > 0) $alerts->push(['type' => 'info', 'icon' => 'fa-shopping-cart', 'text' => $metrics['pending_orders'].' ordrer afventer behandling eller provisioning.']);
        if ($metrics['open_tickets'] > 0) $alerts->push(['type' => 'info', 'icon' => 'fa-ticket', 'text' => $metrics['open_tickets'].' åbne tickets venter på behandling.']);
        return view('admin.index', ['version' => $this->version, 'metrics' => $metrics, 'recentOrders' => $recentOrders, 'alerts' => $alerts]);
    }
}
