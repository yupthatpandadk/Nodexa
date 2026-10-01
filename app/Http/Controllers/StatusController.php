<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(): View
    {
        $components = DB::table('nodexa_status_components')
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activeIncidents = DB::table('nodexa_incidents')
            ->where('published', true)
            ->whereNull('resolved_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        $recentIncidents = DB::table('nodexa_incidents')
            ->where('published', true)
            ->whereNotNull('resolved_at')
            ->orderByDesc('resolved_at')
            ->limit(20)
            ->get();

        $updates = DB::table('nodexa_incident_updates')
            ->whereIn('incident_id', $activeIncidents->pluck('id')->merge($recentIncidents->pluck('id'))->all())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('incident_id');

        $health = DB::table('nodexa_health_checks as h')
            ->joinSub(
                DB::table('nodexa_health_checks')
                    ->selectRaw('node_id, MAX(checked_at) as checked_at')
                    ->groupBy('node_id'),
                'latest',
                function ($join) {
                    $join->on('h.node_id', '=', 'latest.node_id')
                        ->on('h.checked_at', '=', 'latest.checked_at');
                }
            )
            ->join('nodes', 'nodes.id', '=', 'h.node_id')
            ->select(['h.*', 'nodes.name as node_name'])
            ->orderBy('nodes.name')
            ->get();

        $allOperational = $components->every(fn ($component) => $component->status === 'operational')
            && $activeIncidents->isEmpty()
            && $health->every(fn ($check) => $check->status === 'online');

        return view('store.status', compact(
            'components',
            'activeIncidents',
            'recentIncidents',
            'updates',
            'health',
            'allOperational'
        ));
    }
}
