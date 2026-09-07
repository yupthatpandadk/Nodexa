<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Http\Controllers\Controller;

class CommerceController extends Controller
{
    public function billing()
    {
        $invoices = Schema::hasTable('invoices') ? DB::table('invoices')->orderByDesc('id')->limit(100)->get() : collect();
        $orders = Schema::hasTable('billing_orders') ? DB::table('billing_orders')->orderByDesc('id')->limit(100)->get() : collect();
        $products = Schema::hasTable('billing_products') ? DB::table('billing_products')->orderBy('name')->get() : collect();
        return view('admin.commerce.billing', compact('invoices', 'orders', 'products'));
    }

    public function tickets()
    {
        $tickets = Schema::hasTable('tickets') ? DB::table('tickets')->orderByDesc('updated_at')->limit(100)->get() : collect();
        return view('admin.commerce.tickets', compact('tickets'));
    }
}