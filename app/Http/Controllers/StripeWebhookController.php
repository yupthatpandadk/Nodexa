<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Services\Nodexa\StripePaymentService;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripePaymentService $stripe): JsonResponse
    {
        $signature = (string) $request->header('Stripe-Signature', '');

        if ($signature === '') {
            return response()->json(['error' => 'Missing Stripe-Signature'], 400);
        }

        try {
            $stripe->handleWebhook($request->getContent(), $signature);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['error' => 'Webhook processing failed'], 400);
        }

        return response()->json(['received' => true]);
    }
}
