<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class NodexaEventService
{
    public function notify(
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $metadata = []
    ): void {
        try {
            DB::table('nodexa_notifications')->insert([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function audit(
        ?int $userId,
        string $area,
        string $action,
        ?string $description = null,
        ?string $targetType = null,
        string|int|null $targetId = null,
        array $metadata = [],
        ?Request $request = null
    ): void {
        try {
            DB::table('nodexa_audit_logs')->insert([
                'user_id' => $userId,
                'area' => $area,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => is_null($targetId) ? null : (string) $targetId,
                'description' => $description,
                'ip' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 500) ?: null,
                'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function emit(string $event, array $payload, ?int $userId = null): void
    {
        try {
            $webhooks = DB::table('nodexa_webhooks')
                ->where('enabled', true)
                ->where(function ($query) use ($userId) {
                    $query->whereNull('user_id');
                    if ($userId !== null) {
                        $query->orWhere('user_id', $userId);
                    }
                })
                ->get();
        } catch (\Throwable $exception) {
            report($exception);
            return;
        }

        foreach ($webhooks as $webhook) {
            $events = json_decode((string) $webhook->events, true);
            $events = is_array($events) ? $events : [];

            if (!in_array('*', $events, true) && !in_array($event, $events, true)) {
                continue;
            }

            $body = [
                'event' => $event,
                'occurred_at' => now()->toIso8601String(),
                'data' => $payload,
            ];
            $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $signature = hash_hmac('sha256', $json ?: '', (string) $webhook->secret);

            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Nodexa-Event' => $event,
                        'X-Nodexa-Signature' => 'sha256=' . $signature,
                    ])
                    ->withBody($json ?: '{}', 'application/json')
                    ->post($webhook->url);

                DB::table('nodexa_webhook_deliveries')->insert([
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'response_code' => $response->status(),
                    'error' => $response->successful() ? null : substr($response->body(), 0, 2000),
                    'created_at' => now(),
                ]);

                DB::table('nodexa_webhooks')->where('id', $webhook->id)->update([
                    'last_delivery_at' => now(),
                    'last_status' => $response->successful() ? 'success' : 'failed',
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                DB::table('nodexa_webhook_deliveries')->insert([
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'response_code' => null,
                    'error' => substr($exception->getMessage(), 0, 2000),
                    'created_at' => now(),
                ]);

                DB::table('nodexa_webhooks')->where('id', $webhook->id)->update([
                    'last_delivery_at' => now(),
                    'last_status' => 'failed',
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
