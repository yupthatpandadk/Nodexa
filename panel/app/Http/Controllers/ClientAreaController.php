<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientAreaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $services = collect();
        if (Schema::hasTable('servers')) {
            $query = DB::table('servers')->where('owner_id', $user->id);
            $services = $query->orderByDesc('id')->get();
        }

        $invoices = Schema::hasTable('invoices')
            ? DB::table('invoices')->where('user_id', $user->id)->orderByDesc('id')->limit(8)->get()
            : collect();

        $tickets = Schema::hasTable('tickets')
            ? DB::table('tickets')->where('user_id', $user->id)->orderByDesc('id')->limit(8)->get()
            : collect();

        return view('client.index', compact('user', 'services', 'invoices', 'tickets'));
    }
}
