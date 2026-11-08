<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Pterodactyl\Services\Nodexa\SubscriptionCancellationService;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionCancellationService $cancellations)
    {
    }

    public function cancel(Request $request, int $subscription): RedirectResponse
    {
        $data = $request->validate([
            'confirm_cancellation' => 'accepted',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $end = $this->cancellations->requestCancellation(
                $subscription,
                (int) $request->user()->id,
                $data['reason'] ?? null
            );
        } catch (\Throwable $exception) {
            return redirect()->route('store.client.billing')
                ->withErrors(['subscription' => $exception->getMessage()]);
        }

        return redirect()->route('store.client.billing')
            ->with('success', 'Opsigelsen er registreret til ' . $end->format('d.m.Y H:i') .
                '. Der oprettes ikke nye fornyelsesfakturaer. Servicen fortsætter indtil denne dato.');
    }

    public function undo(Request $request, int $subscription): RedirectResponse
    {
        try {
            $this->cancellations->undoCancellation($subscription, (int) $request->user()->id);
        } catch (\Throwable $exception) {
            return redirect()->route('store.client.billing')
                ->withErrors(['subscription' => $exception->getMessage()]);
        }

        return redirect()->route('store.client.billing')
            ->with('success', 'Opsigelsen er fortrudt. Abonnementet fornyes igen.');
    }
}
