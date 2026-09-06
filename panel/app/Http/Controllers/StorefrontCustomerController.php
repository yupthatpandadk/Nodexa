<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

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

        /*
         * Keep the customer area compatible with both older and newer
         * Pterodactyl schemas. Selecting a hard-coded column list caused the
         * whole page to fail whenever a panel version did not contain one of
         * those columns (for example status/installed_at).
         */
        $services = DB::table('servers')
            ->where('owner_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(static function ($service) {
                $service->uuid = $service->uuid ?? '';
                $service->uuidShort = $service->uuidShort ?? substr((string) $service->uuid, 0, 8);
                $service->name = $service->name ?? 'Server';
                $service->description = $service->description ?? '';
                $service->memory = $service->memory ?? 0;
                $service->disk = $service->disk ?? 0;
                $service->cpu = $service->cpu ?? 0;

                if (!isset($service->status) || $service->status === '') {
                    $service->status = !empty($service->suspended) ? 'suspended' : 'active';
                }

                return $service;
            });

        $tickets = $this->loadOptionalCustomerRows('nodexa_tickets', $user->id, function ($query) {
            if (Schema::hasColumn('nodexa_tickets', 'status')) {
                $query->orderByRaw("FIELD(status, 'customer_reply', 'open', 'answered', 'closed')");
            }

            if (Schema::hasColumn('nodexa_tickets', 'last_reply_at')) {
                $query->orderByDesc('last_reply_at');
            } elseif (Schema::hasColumn('nodexa_tickets', 'updated_at')) {
                $query->orderByDesc('updated_at');
            } elseif (Schema::hasColumn('nodexa_tickets', 'id')) {
                $query->orderByDesc('id');
            }

            return $query->limit(25);
        })->map(static function ($ticket) {
            $ticket->id = $ticket->id ?? 0;
            $ticket->subject = $ticket->subject ?? 'Support ticket';
            $ticket->category = $ticket->category ?? 'support';
            $ticket->priority = $ticket->priority ?? 'normal';
            $ticket->status = $ticket->status ?? 'open';
            $ticket->updated_at = $ticket->updated_at ?? null;
            $ticket->last_reply_at = $ticket->last_reply_at ?? $ticket->updated_at;

            return $ticket;
        });

        $invoices = $this->loadOptionalCustomerRows('nodexa_invoices', $user->id, function ($query) {
            if (Schema::hasColumn('nodexa_invoices', 'id')) {
                $query->orderByDesc('id');
            }

            return $query->limit(50);
        })->map(static function ($invoice) {
            $invoice->id = $invoice->id ?? 0;
            $invoice->number = $invoice->number ?? ('#' . $invoice->id);
            $invoice->description = $invoice->description ?? 'Nodexa service';
            $invoice->total = $invoice->total ?? 0;
            $invoice->currency = $invoice->currency ?? 'DKK';
            $invoice->due_at = $invoice->due_at ?? null;
            $invoice->status = $invoice->status ?? 'unpaid';

            return $invoice;
        });

        $stats = [
            'services' => $services->count(),
            'active_services' => $services->filter(static fn ($service) => ($service->status ?? 'active') !== 'suspended')->count(),
            'open_tickets' => $tickets->filter(static fn ($ticket) => in_array($ticket->status ?? '', ['open', 'answered', 'customer_reply'], true))->count(),
            'unpaid_invoices' => $invoices->filter(static fn ($invoice) => in_array($invoice->status ?? '', ['unpaid', 'overdue'], true))->count(),
        ];

        return view('storefront.customer', compact('user', 'services', 'tickets', 'invoices', 'stats', 'section'));
    }

    /**
     * Load data from Nodexa-only tables without taking down the entire customer
     * area if an optional migration has not yet been applied on the live panel.
     */
    private function loadOptionalCustomerRows(string $table, int $userId, callable $configure): Collection
    {
        try {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id')) {
                return collect();
            }

            $query = DB::table($table)->where('user_id', $userId);
            $query = $configure($query);

            return $query->get();
        } catch (Throwable $exception) {
            Log::warning('Nodexa customer area skipped optional data source.', [
                'table' => $table,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }
    }
}
