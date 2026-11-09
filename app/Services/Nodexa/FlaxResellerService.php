<?php

namespace Pterodactyl\Services\Nodexa;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FlaxResellerService
{
    public function __construct(private NodexaEventService $events)
    {
    }

    public function getSettings(): ?object
    {
        return DB::table('nodexa_flax_settings')->orderBy('id')->first();
    }

    public function isConfigured(): bool
    {
        $settings = $this->getSettings();

        return (bool) ($settings?->enabled && $settings?->api_url && $settings?->api_key_encrypted);
    }

    public function maskedKey(): ?string
    {
        $key = $this->apiKey(false);
        if (!$key) {
            return null;
        }

        if (strlen($key) <= 10) {
            return str_repeat('•', strlen($key));
        }

        return substr($key, 0, 4) . str_repeat('•', max(8, strlen($key) - 8)) . substr($key, -4);
    }

    public function saveSettings(string $apiUrl, ?string $apiKey, bool $enabled): void
    {
        $apiUrl = $this->normalizeApiUrl($apiUrl);
        $settings = $this->getSettings();

        $values = [
            'api_url' => $apiUrl,
            'enabled' => $enabled,
            'updated_at' => now(),
        ];

        if ($apiKey !== null && trim($apiKey) !== '') {
            $values['api_key_encrypted'] = Crypt::encryptString(trim($apiKey));
        }

        if ($settings) {
            DB::table('nodexa_flax_settings')->where('id', $settings->id)->update($values);
        } else {
            $values['api_key_encrypted'] ??= null;
            $values['created_at'] = now();
            DB::table('nodexa_flax_settings')->insert($values);
        }
    }

    public function testConnection(): array
    {
        try {
            $response = $this->request('user_servers', 'GET');
            $count = count($this->extractCollection($response, ['servers', 'data']));

            $this->storeTestResult('success', 'Forbindelsen til Flax virker. ' . $count . ' upstream service(s) fundet.');

            return ['success' => true, 'message' => 'Forbindelsen til Flax virker.', 'server_count' => $count];
        } catch (\Throwable $exception) {
            $this->storeTestResult('failed', $exception->getMessage());

            return ['success' => false, 'message' => $exception->getMessage(), 'server_count' => 0];
        }
    }

    public function syncTemplates(): int
    {
        $response = $this->request('virtfusion_templates', 'GET');
        $templates = $this->extractCollection($response, ['templates', 'data']);

        $count = 0;
        foreach ($templates as $template) {
            if (!is_array($template) || !isset($template['id'])) {
                continue;
            }

            $name = trim((string) ($template['name'] ?? ''));
            $version = trim((string) ($template['version'] ?? ''));
            $variant = trim((string) ($template['variant'] ?? ''));
            $label = trim((string) ($template['label'] ?? ''));

            if ($label === '') {
                $label = trim(implode(' ', array_filter([$name, $version, $variant]))) ?: ('Template #' . $template['id']);
            }

            DB::table('nodexa_flax_os_templates')->updateOrInsert(
                ['upstream_id' => (int) $template['id']],
                [
                    'label' => $label,
                    'name' => $name ?: null,
                    'version' => $version ?: null,
                    'variant' => $variant ?: null,
                    'raw_payload' => json_encode($template, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $count++;
        }

        return $count;
    }

    public function createOrder(int $userId, int $productId, string $hostname, int $osTemplateId): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Flax Reseller er ikke konfigureret eller aktiveret.');
        }

        $product = DB::table('nodexa_flax_products')
            ->where('id', $productId)
            ->where('enabled', true)
            ->first();

        if (!$product) {
            throw new RuntimeException('VPS-produktet findes ikke eller er deaktiveret.');
        }

        $template = DB::table('nodexa_flax_os_templates')->where('upstream_id', $osTemplateId)->first();
        if (!$template) {
            throw new RuntimeException('Det valgte operativsystem findes ikke længere. Synkronisér Flax templates igen.');
        }

        $hostname = trim($hostname);
        if ($hostname === '') {
            throw new RuntimeException('Hostname må ikke være tomt.');
        }

        return DB::transaction(function () use ($userId, $product, $hostname, $osTemplateId, $template) {
            $description = CustomerBranding::label($product->name) . ' VPS';

            $subscriptionId = DB::table('nodexa_subscriptions')->insertGetId([
                'user_id' => $userId,
                'server_id' => null,
                'order_id' => null,
                'service_type' => 'flax_vps',
                'service_reference' => null,
                'description' => $description,
                'status' => 'pending',
                'amount' => $product->monthly_price,
                'currency' => $product->currency ?: 'DKK',
                'interval' => 'monthly',
                'next_invoice_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $number = 'NX-VPS-' . now()->format('Ym') . '-' . strtoupper(Str::random(7));
            $invoiceId = DB::table('nodexa_invoices')->insertGetId([
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'number' => $number,
                'status' => 'unpaid',
                'subtotal' => $product->monthly_price,
                'tax' => 0,
                'total' => $product->monthly_price,
                'currency' => $product->currency ?: 'DKK',
                'due_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('nodexa_invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'description' => $description . ' — ' . $hostname . ' — ' . CustomerBranding::label($template->label),
                'quantity' => 1,
                'unit_price' => $product->monthly_price,
                'total' => $product->monthly_price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $serviceId = DB::table('nodexa_flax_services')->insertGetId([
                'user_id' => $userId,
                'product_id' => $product->id,
                'subscription_id' => $subscriptionId,
                'first_invoice_id' => $invoiceId,
                'hostname' => $hostname,
                'os_template_id' => $osTemplateId,
                'status' => 'awaiting_payment',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->update([
                'service_reference' => $serviceId,
                'updated_at' => now(),
            ]);

            $this->events->notify(
                $userId,
                'VPS ordre oprettet',
                $description . ' er oprettet. VPS’en oprettes automatisk af Nodexa, når første faktura er betalt.',
                'billing',
                '/client/vps'
            );

            $this->events->emit('flax_vps.order.created', [
                'service_id' => $serviceId,
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoiceId,
                'product_id' => $product->id,
                'upstream_product_id' => $product->upstream_product_id,
                'os_template_id' => $osTemplateId,
            ], $userId);

            return compact('serviceId', 'subscriptionId', 'invoiceId');
        });
    }

    public function activateInvoice(int $invoiceId): void
    {
        $invoice = DB::table('nodexa_invoices')->where('id', $invoiceId)->first();
        if (!$invoice?->subscription_id || $invoice->status !== 'paid') {
            return;
        }

        $subscription = DB::table('nodexa_subscriptions')->where('id', $invoice->subscription_id)->first();
        if (!$subscription || ($subscription->service_type ?? null) !== 'flax_vps' || !$subscription->service_reference
            || in_array($subscription->status, ['cancel_pending', 'cancelled'], true)
        ) {
            return;
        }

        $service = DB::table('nodexa_flax_services')->where('id', $subscription->service_reference)->first();
        if (!$service || in_array($service->status, ['cancelled', 'terminated'], true)) {
            return;
        }

        if ((int) ($service->last_billed_invoice_id ?? 0) === $invoiceId) {
            if ($service->status === 'suspended') {
                $this->unsuspendSubscription((int) $subscription->id);
            }
            return;
        }

        $product = DB::table('nodexa_flax_products')->where('id', $service->product_id)->first();
        if (!$product) {
            throw new RuntimeException('VPS-produktet til denne service findes ikke længere.');
        }

        // Claim the invoice before touching Flax. This blocks simultaneous
        // callbacks from provisioning or extending the same service twice.
        $claimed = DB::table('nodexa_flax_services')
            ->where('id', $service->id)
            ->whereNull('processing_invoice_id')
            ->update([
                'processing_invoice_id' => $invoiceId,
                'processing_started_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            $current = DB::table('nodexa_flax_services')->where('id', $service->id)->first();

            if ((int) ($current->last_billed_invoice_id ?? 0) === $invoiceId) {
                return;
            }

            if ((int) ($current->processing_invoice_id ?? 0) === $invoiceId) {
                return;
            }

            throw new RuntimeException('VPS-servicen behandler allerede en anden Flax-faktura. Synkronisér servicen og prøv igen.');
        }

        try {
            if (!$service->upstream_server_id) {
                DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                    'status' => 'provisioning',
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

                $response = $this->request('create', 'POST', [
                    'productId' => (int) $product->upstream_product_id,
                    'operatingSystem' => (int) $service->os_template_id,
                ]);

                $responseData = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
                $serverId = $response['server_id'] ?? $responseData['server_id'] ?? null;
                if (!$serverId) {
                    throw new RuntimeException('Flax oprettede ikke VPS’en: server_id mangler i API-responsen.');
                }

                $expiresAt = $response['expires_at'] ?? $responseData['expires_at'] ?? null;
                $serverInfo = $this->fetchServerInfo((string) $serverId, false);

                DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                    'upstream_server_id' => (string) $serverId,
                    'status' => 'active',
                    'upstream_price' => $response['price'] ?? $responseData['price'] ?? null,
                    'upstream_invoice_id' => isset($response['invoice_id']) ? (string) $response['invoice_id'] : null,
                    'upstream_invoice_number' => $response['invoice_number'] ?? null,
                    'last_billed_invoice_id' => $invoiceId,
                    'processing_invoice_id' => null,
                    'processing_started_at' => null,
                    'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
                    'server_info' => $serverInfo ? json_encode($serverInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'last_synced_at' => $serverInfo ? now() : null,
                    'last_error' => null,
                    'provisioned_at' => now(),
                    'suspended_at' => null,
                    'updated_at' => now(),
                ]);

                DB::table('nodexa_subscriptions')->where('id', $subscription->id)->update([
                    'status' => 'active',
                    'next_invoice_at' => now()->addMonth(),
                    'updated_at' => now(),
                ]);

                $this->events->notify(
                    $service->user_id,
                    'Din VPS er klar',
                    $service->hostname . ' er nu oprettet og kan administreres fra Nodexa.',
                    'service',
                    '/client/vps'
                );

                $this->events->emit('flax_vps.provisioned', [
                    'service_id' => $service->id,
                    'upstream_server_id' => (string) $serverId,
                    'invoice_id' => $invoiceId,
                ], $service->user_id);

                return;
            }

            $response = $this->request('extend', 'GET', ['server_id' => $service->upstream_server_id]);
            $responseData = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
            $expiresAt = $response['expires_at'] ?? $responseData['expires_at'] ?? null;

            $hasOverdue = DB::table('nodexa_invoices')
                ->where('subscription_id', $subscription->id)
                ->where('status', 'overdue')
                ->where('id', '!=', $invoiceId)
                ->exists();

            if (!$hasOverdue && $service->status === 'suspended') {
                $this->request('unsuspend', 'POST', ['server_id' => $service->upstream_server_id]);
            }

            DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                'status' => $hasOverdue ? 'suspended' : 'active',
                'upstream_price' => $response['charged'] ?? $response['price'] ?? $service->upstream_price,
                'upstream_invoice_id' => isset($response['invoice_id']) ? (string) $response['invoice_id'] : $service->upstream_invoice_id,
                'upstream_invoice_number' => $response['invoice_number'] ?? $service->upstream_invoice_number,
                'last_billed_invoice_id' => $invoiceId,
                'processing_invoice_id' => null,
                'processing_started_at' => null,
                'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : $service->expires_at,
                'last_error' => null,
                'suspended_at' => $hasOverdue ? ($service->suspended_at ?: now()) : null,
                'updated_at' => now(),
            ]);

            DB::table('nodexa_subscriptions')->where('id', $subscription->id)->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);

            $this->events->notify(
                $service->user_id,
                'VPS abonnement fornyet',
                $service->hostname . ' er forlænget for den nye faktureringsperiode.',
                'billing',
                '/client/vps'
            );
        } catch (\Throwable $exception) {
            DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                'status' => $service->upstream_server_id ? $service->status : 'provisioning_failed',
                'processing_invoice_id' => null,
                'processing_started_at' => null,
                'last_error' => substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function suspendSubscription(int $subscriptionId): void
    {
        $subscription = DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->first();
        if (!$subscription || ($subscription->service_type ?? null) !== 'flax_vps' || !$subscription->service_reference) {
            return;
        }

        $service = DB::table('nodexa_flax_services')->where('id', $subscription->service_reference)->first();
        if (!$service || !$service->upstream_server_id || in_array($service->status, ['cancelled', 'terminated', 'awaiting_payment'], true)) {
            return;
        }

        if ($service->status !== 'suspended') {
            $this->request('suspend', 'POST', ['server_id' => $service->upstream_server_id]);
        }

        DB::table('nodexa_flax_services')->where('id', $service->id)->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'updated_at' => now(),
        ]);

        $this->events->notify(
            $service->user_id,
            'VPS suspenderet',
            $service->hostname . ' er midlertidigt suspenderet på grund af en forfalden faktura.',
            'warning',
            '/client/billing'
        );
    }

    public function unsuspendSubscription(int $subscriptionId): void
    {
        $subscription = DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->first();
        if (!$subscription || ($subscription->service_type ?? null) !== 'flax_vps' || !$subscription->service_reference) {
            return;
        }

        $hasOverdue = DB::table('nodexa_invoices')
            ->where('subscription_id', $subscriptionId)
            ->where('status', 'overdue')
            ->exists();

        if ($hasOverdue) {
            return;
        }

        $service = DB::table('nodexa_flax_services')->where('id', $subscription->service_reference)->first();
        if (!$service || !$service->upstream_server_id || $service->status !== 'suspended') {
            return;
        }

        $this->request('unsuspend', 'POST', ['server_id' => $service->upstream_server_id]);

        DB::table('nodexa_flax_services')->where('id', $service->id)->update([
            'status' => 'active',
            'suspended_at' => null,
            'last_error' => null,
            'updated_at' => now(),
        ]);

        $this->events->notify(
            $service->user_id,
            'VPS genaktiveret',
            $service->hostname . ' er genaktiveret, fordi de udestående fakturaer er betalt.',
            'service',
            '/client/vps'
        );
    }

    public function retryPaidService(int $serviceId): void
    {
        $service = DB::table('nodexa_flax_services')->where('id', $serviceId)->first();
        if (!$service?->subscription_id) {
            throw new RuntimeException('VPS-servicen har ikke et abonnement.');
        }

        $invoice = DB::table('nodexa_invoices')
            ->where('subscription_id', $service->subscription_id)
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        if (!$invoice) {
            throw new RuntimeException('Der findes ingen betalt faktura at provisionere servicen fra.');
        }

        if ($service->processing_invoice_id) {
            $startedAt = $service->processing_started_at ? Carbon::parse($service->processing_started_at) : null;

            if ($startedAt && $startedAt->greaterThan(now()->subMinutes(10))) {
                throw new RuntimeException('Flax-handlingen er stadig markeret som aktiv. Vent op til 10 minutter før manuel retry.');
            }

            DB::table('nodexa_flax_services')->where('id', $serviceId)->update([
                'processing_invoice_id' => null,
                'processing_started_at' => null,
                'updated_at' => now(),
            ]);
        }

        $this->activateInvoice($invoice->id);
    }

    public function syncService(int $serviceId): array
    {
        $service = DB::table('nodexa_flax_services')->where('id', $serviceId)->first();
        if (!$service || !$service->upstream_server_id) {
            throw new RuntimeException('VPS’en er endnu ikke provisioneret hos Flax.');
        }

        $info = $this->fetchServerInfo($service->upstream_server_id, true);

        DB::table('nodexa_flax_services')->where('id', $serviceId)->update([
            'server_info' => json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'last_synced_at' => now(),
            'last_error' => null,
            'updated_at' => now(),
        ]);

        return $info;
    }

    public function syncStaleServices(int $minutes = 15, int $limit = 25): int
    {
        if (!$this->isConfigured()) {
            return 0;
        }

        $services = DB::table('nodexa_flax_services')
            ->whereNotNull('upstream_server_id')
            ->whereIn('status', ['active', 'suspended'])
            ->where(function ($query) use ($minutes) {
                $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subMinutes($minutes));
            })
            ->orderBy('last_synced_at')
            ->limit($limit)
            ->get();

        $synced = 0;
        foreach ($services as $service) {
            try {
                $this->syncService($service->id);
                $synced++;
            } catch (\Throwable $exception) {
                DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                    'last_error' => substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
                report($exception);
            }
        }

        return $synced;
    }

    /**
     * Retrieve the latest VPS password saved by the reseller API.
     * This action does NOT change the server's login.
     *
     * The upstream API limits this action to VPS resources, authorized owners
     * and developer access. The Nodexa owner check is enforced independently.
     */
    public function getStoredOsPassword(int $serviceId, int $userId): ?string
    {
        $service = DB::table('nodexa_flax_services')
            ->where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service || !in_array($service->status, ['active', 'suspended'], true)
            || !$service->upstream_server_id) {
            throw new RuntimeException('VPS-serveren er ikke tilgængelig for denne konto.');
        }

        // API docs: POST action=get_password, server_id=<upstream VPS ID>.
        // data.available=false means the provider has no saved credential.
        // Never log the response: data.password is a sensitive secret.
        try {
            $result = $this->request('get_password', 'POST', [
                'server_id' => (string) $service->upstream_server_id,
            ]);
        } catch (\Throwable $exception) {
            throw VpsCredentialLookupException::fromProvider($exception);
        }

        $returnedServerId = data_get($result, 'data.server_id');
        if ($returnedServerId !== null
            && (string) $returnedServerId !== (string) $service->upstream_server_id) {
            throw new VpsCredentialLookupException('server_mismatch');
        }

        if (in_array(data_get($result, 'data.available'), [false, 0, 'false', '0'], true)) {
            return null;
        }

        $password = data_get($result, 'data.password');

        if (!is_string($password) || $password === '') {
            return null;
        }

        if (strlen($password) > 1024) {
            throw new VpsCredentialLookupException('invalid_response');
        }

        return $password;
    }

    /**
     * Reset the OS credential via the documented reseller action, then fetch
     * the latest stored password using get_password.
     *
     * Some resets are asynchronous: a still-unchanged stored password must
     * not be offered as a successfully reset password.
     *
     * @return array{password:?string,queued:bool,previous_hash:?string}
     */
    public function resetOsPassword(int $serviceId, int $userId, string $osUser, ?string $knownPreviousHash = null): array
    {
        if (!$this->supportsPasswordReset()) {
            throw new RuntimeException('Automatisk password-reset er midlertidigt utilgængelig.');
        }

        if (!in_array($osUser, ['Administrator', 'root'], true)) {
            throw new RuntimeException('Operativsystemets login-bruger blev ikke genkendt.');
        }

        $service = DB::table('nodexa_flax_services')
            ->where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service || $service->status !== 'active' || !$service->upstream_server_id) {
            throw new RuntimeException('VPS-serveren er ikke aktiv eller tilhører ikke din konto.');
        }

        // Obtain a baseline so a queued reset cannot accidentally expose the
        // prior password returned by an eventually consistent API.
        $oldPassword = null;
        try {
            $oldPassword = $this->getStoredOsPassword($serviceId, $userId);
        } catch (\Throwable $exception) {
            // Reading the old password is optional and must not block reset.
        }

        try {
            // Documented action and parameters: resetPassword, server_id, user.
            // The API uses root by default; specify Administrator for Windows.
            $result = $this->request('resetPassword', 'POST', [
                'server_id' => (string) $service->upstream_server_id,
                'user' => $osUser,
            ]);
        } catch (\Throwable $exception) {
            // Avoid exposing raw provider payloads, which may contain secrets.
            throw new RuntimeException('Password-reset kunne ikke bekræftes. Kontakt Nodexa Support før et nyt forsøg.');
        }

        $fetched = null;
        try {
            // Always fetch from the dedicated provider API after reset.
            $fetched = $this->getStoredOsPassword($serviceId, $userId);
        } catch (\Throwable $exception) {
            // Reset may already have been accepted; allow safe later retrieval.
        }

        $previousHash = $oldPassword !== null
            ? hash_hmac('sha256', $oldPassword, (string) config('app.key'))
            : $knownPreviousHash;

        $changed = $fetched !== null
            && ($previousHash === null || !hash_equals(
                $previousHash,
                hash_hmac('sha256', $fetched, (string) config('app.key'))
            ));

        // Only a fresh result from get_password counts as the new credential.
        // A reset acknowledgment may precede actual password persistence.
        $password = $changed ? $fetched : null;
        $queued = data_get($result, 'data.queueId') !== null
            || data_get($result, 'queueId') !== null
            || in_array(strtolower((string) data_get($result, 'status')), ['queued', 'pending'], true);

        return [
            'password' => $password,
            'queued' => $queued || $password === null,
            'previous_hash' => $previousHash,
        ];
    }

    public function supportsPasswordReset(): bool
    {
        // Flax has documented the resetPassword reseller action.
        return $this->isConfigured();
    }

    public function power(int $serviceId, int $userId, string $action): void
    {
        $allowed = ['boot', 'shutdown', 'restart', 'poweroff'];
        if (!in_array($action, $allowed, true)) {
            throw new RuntimeException('Ugyldig VPS-handling.');
        }

        $service = DB::table('nodexa_flax_services')
            ->where('id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        if (!$service || !$service->upstream_server_id || $service->status !== 'active') {
            throw new RuntimeException('VPS’en er ikke aktiv eller tilhører ikke denne konto.');
        }

        $this->request($action, 'POST', ['server_id' => $service->upstream_server_id]);

        try {
            $this->syncService($serviceId);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function adminAction(int $serviceId, string $action): void
    {
        $service = DB::table('nodexa_flax_services')->where('id', $serviceId)->first();
        if (!$service) {
            throw new RuntimeException('VPS-servicen blev ikke fundet.');
        }

        if ($action === 'retry') {
            $this->retryPaidService($serviceId);
            return;
        }

        if ($action === 'sync') {
            $this->syncService($serviceId);
            return;
        }

        if ($action === 'terminate') {
            $this->terminateService($serviceId);
            return;
        }

        if (!$service->upstream_server_id) {
            throw new RuntimeException('VPS’en er ikke provisioneret hos Flax endnu.');
        }

        if (in_array($action, ['boot', 'shutdown', 'restart', 'poweroff'], true)) {
            $this->request($action, 'POST', ['server_id' => $service->upstream_server_id]);
        } elseif ($action === 'suspend') {
            $this->request('suspend', 'POST', ['server_id' => $service->upstream_server_id]);
            DB::table('nodexa_flax_services')->where('id', $serviceId)->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ($action === 'unsuspend') {
            $this->request('unsuspend', 'POST', ['server_id' => $service->upstream_server_id]);
            DB::table('nodexa_flax_services')->where('id', $serviceId)->update([
                'status' => 'active',
                'suspended_at' => null,
                'updated_at' => now(),
            ]);
        } else {
            throw new RuntimeException('Ugyldig admin-handling.');
        }

        try {
            $this->syncService($serviceId);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function terminateService(int $serviceId, bool $cancelOpenInvoices = true): void
    {
        $service = DB::table('nodexa_flax_services')->where('id', $serviceId)->first();
        if (!$service) {
            throw new RuntimeException('VPS-servicen blev ikke fundet.');
        }

        if ($service->upstream_server_id && $service->status !== 'terminated') {
            $this->request('terminate', 'POST', ['server_id' => $service->upstream_server_id]);
        }

        DB::transaction(function () use ($service, $cancelOpenInvoices) {
            DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                'status' => 'terminated',
                'terminated_at' => now(),
                'updated_at' => now(),
            ]);

            if ($service->subscription_id) {
                DB::table('nodexa_subscriptions')->where('id', $service->subscription_id)->update([
                    'status' => 'cancelled',
                    'cancel_at' => now(),
                    'next_invoice_at' => null,
                    'updated_at' => now(),
                ]);

                // Cancellation at paid-through period end does not forgive
                // invoices issued earlier. Administrative termination may
                // still cancel open invoices explicitly.
                if ($cancelOpenInvoices) {
                    DB::table('nodexa_invoices')
                        ->where('subscription_id', $service->subscription_id)
                        ->whereIn('status', ['unpaid', 'overdue'])
                        ->update(['status' => 'cancelled', 'updated_at' => now()]);
                }
            }
        });

        $this->events->notify(
            $service->user_id,
            'VPS afsluttet',
            $service->hostname . ' er afsluttet efter opsigelsen.',
            'service',
            '/client/vps'
        );
    }

    public function primaryIp(?string $serverInfo): ?string
    {
        if (!$serverInfo) {
            return null;
        }

        $data = json_decode($serverInfo, true);
        if (!is_array($data)) {
            return null;
        }

        // Flax/VirtFusion responses may contain an array rather than a
        // string in ips[0]. Do not let that raise a TypeError on /client/vps.
        $candidates = [
            $data['network']['interfaces'][0]['ipv4'][0]['address'] ?? null,
            $data['ips'][0]['address'] ?? null,
            $data['ips'][0]['ip'] ?? null,
            $data['ips'][0] ?? null,
            $data['ip'] ?? null,
            $data['primary_ip'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    public function remoteRunning(?string $serverInfo): ?bool
    {
        if (!$serverInfo) {
            return null;
        }

        $data = json_decode($serverInfo, true);
        if (!is_array($data)) {
            return null;
        }

        $value = $data['remoteState']['running']
            ?? $data['remote_state']['running']
            ?? $data['running']
            ?? null;

        return is_bool($value) ? $value : null;
    }

    private function fetchServerInfo(string $serverId, bool $throw = true): ?array
    {
        try {
            $response = $this->request('info', 'GET', ['server_id' => $serverId]);

            if (isset($response['server']) && is_array($response['server'])) {
                return $response['server'];
            }

            if (isset($response['data']) && is_array($response['data'])) {
                if (isset($response['data']['server']) && is_array($response['data']['server'])) {
                    return $response['data']['server'];
                }

                return $response['data'];
            }

            return $response;
        } catch (\Throwable $exception) {
            if ($throw) {
                throw $exception;
            }

            report($exception);
            return null;
        }
    }

    private function request(string $action, string $method = 'GET', array $params = []): array
    {
        $settings = $this->getSettings();
        if (!$settings?->api_url || !$settings?->api_key_encrypted) {
            throw new RuntimeException('Flax Reseller API er ikke konfigureret.');
        }

        $key = $this->apiKey();
        $baseUrl = $this->normalizeApiUrl($settings->api_url);
        $method = strtoupper($method);

        // New reseller API docs: POST directly to the API root, with key,
        // action and server_id in the FORM BODY for get_password/resetPassword.
        // Keep all existing VPS billing/power actions on the legacy endpoint,
        // since those integrations are already operational.
        $useDocumentedPasswordApi = in_array($action, ['get_password', 'resetPassword'], true);
        if ($useDocumentedPasswordApi) {
            $requestUrl = rtrim($baseUrl, '/') . '/';
            $body = array_merge([
                'key' => $key,
                'action' => $action,
            ], $params);
        } else {
            $endpoint = $this->buildEndpointUrl($baseUrl);
            $query = array_merge([
                'key' => $key,
                'action' => $action,
            ], $params);
            $requestUrl = $endpoint . '?' . http_build_query($query);
            $body = $params;
        }

        try {
            $client = Http::timeout($method === 'GET' ? 15 : 20)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $key,
                    'User-Agent' => 'Nodexa Flax Reseller',
                ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'referer' => false,
                        'track_redirects' => true,
                    ],
                ]);

            if ($method === 'GET') {
                $response = $client->get($requestUrl);
            } else {
                $response = $client
                    ->asForm()
                    ->post($requestUrl, $body);
            }

            // Password lookup is read-only. The provider has historically
            // served the same reseller API via /api-endpoint.php as well as
            // the API root. Only retry a *read* on an unmistakable endpoint
            // mismatch, never when credentials/ownership are rejected.
            //
            // Do NOT retry resetPassword here: it is a state-changing action
            // and a timeout or ambiguous response could cause a second reset.
            if ($action === 'get_password' && (
                in_array($response->status(), [404, 405], true)
                || ($response->successful() && !is_array($response->json()))
            )) {
                $fallback = $this->buildEndpointUrl($baseUrl) . '?' . http_build_query(
                    array_merge(['key' => $key, 'action' => $action], $params)
                );
                $response = $client->asForm()->post($fallback, $params);
            }
        } catch (\Throwable $exception) {
            $message = str_replace($key, '[redacted]', $exception->getMessage());
            throw new RuntimeException('Kunne ikke kontakte Flax API: ' . $message, 0, $exception);
        }

        return $this->decodeResponse($response, $action);
    }

    private function decodeResponse(Response $response, string $action): array
    {
        $json = $response->json();
        $key = $this->apiKey(false);

        if (!is_array($json)) {
            $contentType = trim((string) $response->header('Content-Type'));
            $preview = strip_tags((string) $response->body());
            $preview = preg_replace('/\s+/', ' ', $preview) ?: '';
            $preview = trim($preview);

            if ($key && $preview !== '') {
                $preview = str_replace($key, '[redacted]', $preview);
            }

            if (mb_strlen($preview) > 180) {
                $preview = mb_substr($preview, 0, 180) . '…';
            }

            $details = $contentType !== '' ? ' · Content-Type: ' . $contentType : '';
            if ($preview !== '') {
                $details .= ' · Svar: ' . $preview;
            }

            throw new RuntimeException(
                'Flax API returnerede ugyldigt JSON (HTTP ' . $response->status() . ')' . $details
            );
        }

        if ($key) {
            array_walk_recursive($json, function (&$value) use ($key) {
                if (is_string($value)) {
                    $value = str_replace($key, '[redacted]', $value);
                }
            });
        }

        if (!$response->successful()) {
            $message = $json['error'] ?? $json['message'] ?? $json['data'] ?? ('HTTP ' . $response->status());
            throw new RuntimeException('Flax API fejl: ' . (is_scalar($message) ? $message : json_encode($message)));
        }

        if (array_key_exists('success', $json) && $json['success'] === false) {
            $message = $json['error'] ?? $json['message'] ?? 'Ukendt fejl';
            throw new RuntimeException('Flax API fejl: ' . (is_scalar($message) ? $message : json_encode($message)));
        }

        if (($json['status'] ?? null) === 'error') {
            $message = $json['data'] ?? $json['message'] ?? 'Ukendt fejl';
            throw new RuntimeException('Flax API fejl: ' . (is_scalar($message) ? $message : json_encode($message)));
        }

        return $json;
    }

    private function extractCollection(array $response, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $response[$key] ?? null;

            if (is_array($value) && array_is_list($value)) {
                return $value;
            }

            if (is_array($value) && isset($value['data']) && is_array($value['data']) && array_is_list($value['data'])) {
                return $value['data'];
            }

            if (is_array($value) && isset($value['templates']) && is_array($value['templates'])) {
                return $value['templates'];
            }
        }

        if (array_is_list($response)) {
            return $response;
        }

        return [];
    }

    private function storeTestResult(string $status, string $message): void
    {
        $settings = $this->getSettings();
        if (!$settings) {
            return;
        }

        DB::table('nodexa_flax_settings')->where('id', $settings->id)->update([
            'last_tested_at' => now(),
            'last_test_status' => $status,
            'last_test_message' => substr($message, 0, 4000),
            'updated_at' => now(),
        ]);
    }

    private function apiKey(bool $required = true): ?string
    {
        $settings = $this->getSettings();

        if (!$settings?->api_key_encrypted) {
            if ($required) {
                throw new RuntimeException('Flax API-key mangler.');
            }

            return null;
        }

        try {
            return Crypt::decryptString($settings->api_key_encrypted);
        } catch (\Throwable $exception) {
            if ($required) {
                throw new RuntimeException('Flax API-key kunne ikke dekrypteres. Gem API-key igen i Admin.', 0, $exception);
            }

            return null;
        }
    }

    private function normalizeApiUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('Flax API URL må ikke være tom.');
        }

        if (!str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException('Flax API URL skal bruge HTTPS.');
        }

        $url = preg_replace('/[?#].*$/', '', $url) ?: $url;

        // Accept either the Flax API base host or a full legacy endpoint path.
        $url = preg_replace('#/api-endpoint\.php/?$#i', '', $url) ?: $url;

        return rtrim($url, '/');
    }

    private function buildEndpointUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/api-endpoint.php';
    }

}
