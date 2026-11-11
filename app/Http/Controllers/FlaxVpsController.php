<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Pterodactyl\Services\Nodexa\FlaxResellerService;
use Pterodactyl\Services\Nodexa\GlobalStoreSaleService;

class FlaxVpsController extends Controller
{
    public function __construct(
        private FlaxResellerService $flax,
        private GlobalStoreSaleService $sale,
    ) {
    }

    public function index(): View
    {
        $products = DB::table('nodexa_flax_products')
            ->where('enabled', true)
            ->orderByDesc('featured')
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get();

        return view(app(\Pterodactyl\Services\Nodexa\FrontendThemeService::class)->template('vps'), [
            'products' => $products,
            'configured' => $this->flax->isConfigured(),
            'globalSale' => $this->sale->active(),
        ]);
    }

    public function show(string $product): View
    {
        $plan = DB::table('nodexa_flax_products')
            ->where('slug', $product)
            ->where('enabled', true)
            ->first();

        abort_unless($plan, 404);

        $templates = DB::table('nodexa_flax_os_templates')
            ->orderBy('name')
            ->orderBy('version')
            ->orderBy('variant')
            ->get();

        return view('store.vps.product', [
            'product' => $plan,
            'templates' => $templates,
            'configured' => $this->flax->isConfigured(),
            'globalSale' => $this->sale->active(),
        ]);
    }

    public function order(Request $request, string $product): RedirectResponse
    {
        $plan = DB::table('nodexa_flax_products')
            ->where('slug', $product)
            ->where('enabled', true)
            ->first();

        abort_unless($plan, 404);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'min:3', 'max:190', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]*[A-Za-z0-9]$/'],
            'os_template_id' => 'required|integer|exists:nodexa_flax_os_templates,upstream_id',
        ]);

        try {
            $result = $this->flax->createOrder(
                $request->user()->id,
                $plan->id,
                trim($data['hostname']),
                (int) $data['os_template_id']
            );
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withInput()->withErrors(['vps' => \Pterodactyl\Services\Nodexa\CustomerBranding::serviceError()]);
        }

        return redirect()->route('store.vps.checkout', $result['serviceId']);
    }

    public function checkout(Request $request, int $service): View
    {
        $serviceRow = DB::table('nodexa_flax_services as s')
            ->join('nodexa_flax_products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('nodexa_flax_os_templates as os', 'os.upstream_id', '=', 's.os_template_id')
            ->leftJoin('nodexa_invoices as i', 'i.id', '=', 's.first_invoice_id')
            ->where('s.id', $service)
            ->where('s.user_id', $request->user()->id)
            ->select([
                's.*',
                'p.name as product_name',
                'p.description as product_description',
                'p.memory_mb',
                'p.vcpu',
                'p.disk_gb',
                'p.ipv4_count',
                'p.bandwidth',
                'p.monthly_price',
                'p.currency',
                'os.label as os_label',
                'i.number as invoice_number',
                'i.status as invoice_status',
                'i.due_at as invoice_due_at',
            ])
            ->first();

        abort_unless($serviceRow, 404);

        return view('store.vps.checkout', ['service' => $serviceRow]);
    }

    public function client(Request $request): View
    {
        $services = DB::table('nodexa_flax_services as s')
            ->join('nodexa_flax_products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('nodexa_flax_os_templates as os', 'os.upstream_id', '=', 's.os_template_id')
            ->leftJoin('nodexa_subscriptions as sub', 'sub.id', '=', 's.subscription_id')
            ->where('s.user_id', $request->user()->id)
            ->select([
                's.*',
                'p.name as product_name',
                'p.memory_mb',
                'p.vcpu',
                'p.disk_gb',
                'p.ipv4_count',
                'p.bandwidth',
                'p.monthly_price',
                'p.currency',
                'os.label as os_label',
                'sub.next_invoice_at',
                'sub.status as subscription_status',
            ])
            ->orderByDesc('s.id')
            ->get();

        foreach ($services as $service) {
            $service->primary_ip = $this->flax->primaryIp($service->server_info);
            $service->remote_running = $this->flax->remoteRunning($service->server_info);
        }

        return view('store.client.vps', compact('services'));
    }

    /**
     * Individual customer VPS control panel. The owner filter is mandatory:
     * an authenticated customer must never view another customer's VPS.
     */
    public function clientService(Request $request, int $service): View
    {
        $serviceRow = DB::table('nodexa_flax_services as s')
            ->leftJoin('nodexa_flax_products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('nodexa_flax_os_templates as os', 'os.upstream_id', '=', 's.os_template_id')
            ->leftJoin('nodexa_subscriptions as sub', 'sub.id', '=', 's.subscription_id')
            ->where('s.id', $service)
            ->where('s.user_id', $request->user()->id)
            ->select([
                's.*',
                'p.name as product_name',
                'p.vcpu',
                'p.memory_mb',
                'p.disk_gb',
                'p.ipv4_count',
                'p.bandwidth',
                'p.monthly_price',
                'p.currency',
                'os.label as os_label',
                'sub.next_invoice_at',
                'sub.status as subscription_status',
            ])
            ->first();

        abort_unless($serviceRow, 404);

        return view('store.client.vps-show', [
            'service' => $serviceRow,
            'primaryIp' => $this->flax->primaryIp($serviceRow->server_info),
            'running' => $this->flax->remoteRunning($serviceRow->server_info),
            'osInfo' => $this->osInfo($serviceRow->server_info, $serviceRow->os_label),
            'canResetPassword' => $this->flax->supportsPasswordReset()
                && Schema::hasColumn('nodexa_flax_services', 'credential_reset_pending_at')
                && Schema::hasColumn('nodexa_flax_services', 'credential_reset_previous_hash'),
            'hasPassword' => Schema::hasColumn('nodexa_flax_services', 'credential_password_encrypted')
                && !empty($serviceRow->credential_password_encrypted),
            'passwordResetPending' => !empty($serviceRow->credential_reset_pending_at),
        ]);
    }

    /**
     * Identify the installed OS from the upstream server information where
     * available. Fall back to the OS template registered when the VPS was
     * provisioned, and never guess an unrecognized distribution.
     */
    /**
     * Lazily retrieve VPS backup metadata. Never expose upstream response
     * bodies or storage URLs to the browser.
     */
    public function clientBackups(Request $request, int $service): JsonResponse
    {
        // Use the same owner restriction as the password and power controls.
        $owned = DB::table('nodexa_flax_services')
            ->where('id', $service)
            ->where('user_id', $request->user()->id)
            ->first(['id', 'status', 'upstream_server_id']);

        abort_unless($owned, 404);

        if (!$owned->upstream_server_id
            || !in_array($owned->status, ['active', 'suspended'], true)) {
            return response()->json([
                'message' => 'Backups kan først vises, når VPS-serveren er oprettet.',
            ], 409)->header('Cache-Control', 'private, no-store, max-age=0');
        }

        try {
            $backups = $this->flax->listVpsBackups(
                $service,
                (int) $request->user()->id
            );

            return response()->json(['backups' => $backups])
                ->header('Cache-Control', 'private, no-store, max-age=0')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Nodexa VPS backup list unavailable', [
                'service_id' => $service,
                'reason' => 'provider_lookup_failed',
            ]);

            return response()->json([
                'message' => 'Backups kunne ikke hentes. Prøv igen senere, eller kontakt Nodexa Support.',
            ], 503)->header('Cache-Control', 'private, no-store, max-age=0')
                ->header('X-Content-Type-Options', 'nosniff');
        }
    }

    private function osInfo(?string $serverInfo, ?string $templateLabel): array
    {
        $info = json_decode((string) $serverInfo, true);
        $info = is_array($info) ? $info : [];

        $liveValues = [
            data_get($info, 'operatingSystem.label'),
            data_get($info, 'operatingSystem.name'),
            data_get($info, 'os.label'),
            data_get($info, 'os.name'),
            data_get($info, 'template.label'),
            data_get($info, 'template.name'),
            data_get($info, 'operatingSystem'),
            data_get($info, 'os'),
        ];

        $known = '/windows|ubuntu|debian|fedora|almalinux|alma linux|rocky linux|rockylinux|centos|arch linux|archlinux|freebsd|open.?suse|kali linux|linux/i';
        $liveName = null;

        foreach ($liveValues as $value) {
            if (is_string($value) && preg_match($known, $value)) {
                $liveName = trim($value);
                break;
            }
        }

        $template = trim((string) $templateLabel);
        $name = $liveName ?: ($template !== '' ? $template : 'Operativsystem ikke registreret');

        // Explicit whitelist: no file name or path is sourced from the API.
        $slug = 'generic';
        $lower = mb_strtolower($name);
        foreach ([
            'windows' => ['windows'],
            'ubuntu' => ['ubuntu'],
            'debian' => ['debian'],
            'fedora' => ['fedora'],
            'almalinux' => ['almalinux', 'alma linux'],
            'rockylinux' => ['rocky linux', 'rockylinux'],
            'centos' => ['centos'],
            'archlinux' => ['arch linux', 'archlinux'],
            'freebsd' => ['freebsd'],
            'opensuse' => ['opensuse', 'open suse'],
            'kalilinux' => ['kali linux', 'kalilinux'],
            'linux' => ['linux'],
        ] as $icon => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($lower, $alias)) {
                    $slug = $icon;
                    break 2;
                }
            }
        }

        return [
            'name' => $name,
            'icon' => $slug,
            'source' => $liveName ? 'Aktuel serverinformation' : 'Registreret OS-skabelon',
            'verified_live' => $liveName !== null,
        ];
    }

    /**
     * Request an actual VPS password reset with Flax and persist the newly
     * returned password encrypted. Never claim success without a returned
     * credential, and never expose it in a redirect URL or session flash.
     */
    public function resetPassword(Request $request, int $service): RedirectResponse
    {
        $owned = $this->ownedPasswordService($request, $service);
        if (!$this->credentialResetStorageReady()) {
            \Illuminate\Support\Facades\Log::warning('Nodexa VPS password reset prerequisites missing', [
                'service_id' => $service,
                'reason' => 'database_migration_pending',
            ]);
            return back()->withErrors([
                'vps' => 'Passwordfunktionen mangler en systemopdatering. Kontakt Nodexa Support.',
            ]);
        }
        if ($owned->status !== 'active') {
            return back()->withErrors(['vps' => 'Kun aktive VPS-servere kan nulstilles.']);
        }

        if (!empty($owned->credential_reset_pending_at)) {
            return back()->withErrors([
                'vps' => 'En nulstilling afventer stadig nyt password. Tryk Hent / Vis i stedet for at nulstille igen.',
            ]);
        }

        if (!$this->flax->supportsPasswordReset()) {
            return back()->withErrors(['vps' => 'Automatisk nulstilling er ikke tilgængelig lige nu. Kontakt Nodexa Support.']);
        }

        $os = DB::table('nodexa_flax_os_templates')
            ->where('upstream_id', $owned->os_template_id)->value('label');
        $osInfo = $this->osInfo($owned->server_info, is_string($os) ? $os : '');
        $osName = mb_strtolower((string) $osInfo['name']);

        if (str_contains($osName, 'windows')) {
            $osUser = 'Administrator';
        } elseif (preg_match('/ubuntu|debian|fedora|almalinux|alma linux|rocky|centos|arch|kali|suse|linux/', $osName)) {
            $osUser = 'root';
        } else {
            return back()->withErrors(['vps' => 'OS blev ikke genkendt. Password kan ikke nulstilles sikkert.']);
        }

        $rateKey = 'nodexa:vps:password-reset:' . (int) $request->user()->id . ':' . $service;
        if (RateLimiter::tooManyAttempts($rateKey, 2)) {
            return back()->withErrors(['vps' => 'For mange resetforsøg. Prøv igen senere.']);
        }

        $lock = Cache::lock('nodexa:vps:password-reset:lock:' . $service, 90);
        if (!$lock->get()) {
            return back()->withErrors(['vps' => 'Password-reset er allerede i gang for denne VPS.']);
        }

        $previousHash = null;
        try {
            RateLimiter::hit($rateKey, 900);
            if (!empty($owned->credential_password_encrypted)) {
                try {
                    $previousPassword = Crypt::decryptString($owned->credential_password_encrypted);
                    $previousHash = hash_hmac('sha256', $previousPassword, (string) config('app.key'));
                } catch (\Throwable $ignored) {
                    // An unreadable local copy cannot validate the prior code.
                }
            }

            $result = $this->flax->resetOsPassword(
                $service, (int) $request->user()->id, $osUser, $previousHash
            );
            $newPassword = $result['password'] ?? null;

            if (is_string($newPassword) && $newPassword !== '') {
                DB::table('nodexa_flax_services')
                    ->where('id', $service)
                    ->where('user_id', $request->user()->id)
                    ->update([
                        'credential_password_encrypted' => Crypt::encryptString($newPassword),
                        'credential_reset_pending_at' => null,
                        'credential_reset_previous_hash' => null,
                        'updated_at' => now(),
                    ]);

                $message = !empty($result['queued'])
                    ? 'Nulstillingen er sat i kø, men den nye adgangskode er allerede gemt i VPS-systemet. Den kan vises i Nodexa og bliver anvendelig, når serveren har gennemført nulstillingen.'
                    : 'VPS-passwordet er nulstillet og gemt krypteret i Nodexa. Tryk Vis for at hente den senest gemte adgangskode.';

                return redirect()->route('store.client.vps.show', $service)
                    ->with('success', $message);
            }

            // A reset can succeed before the provider stores the generated
            // credential. Keep the last password fingerprint and withhold it
            // from the customer until get_password reports a different value.
            $previousHash = $result['previous_hash'] ?? null;
            if ($previousHash === null && !empty($owned->credential_password_encrypted)) {
                try {
                    $oldSecret = Crypt::decryptString($owned->credential_password_encrypted);
                    $previousHash = hash_hmac('sha256', $oldSecret, (string) config('app.key'));
                } catch (\Throwable $ignored) {
                    // Do not expose decryption failures or untrusted secrets.
                }
            }

            DB::table('nodexa_flax_services')
                ->where('id', $service)
                ->where('user_id', $request->user()->id)
                ->update([
                    'credential_password_encrypted' => null,
                    'credential_reset_pending_at' => now(),
                    'credential_reset_previous_hash' => $previousHash,
                    'updated_at' => now(),
                ]);

            return redirect()->route('store.client.vps.show', $service)
                ->with('success', 'Nulstilling er startet. Den nye adgangskode er endnu ikke tilgængelig. Tryk Vis password igen om lidt for at hente den automatisk.');
        } catch (\Pterodactyl\Services\Nodexa\VpsPasswordResetException $exception) {
            // Never log raw API payloads, URLs, credentials or exception chains.
            \Illuminate\Support\Facades\Log::warning('Nodexa VPS password reset failed', [
                'service_id' => $service,
                'reason' => $exception->reason,
                'http_status' => $exception->httpStatus,
                'response_type' => $exception->responseType,
                'api_transport' => $exception->transport,
            ]);

            if ($exception->isAmbiguous()) {
                // A timeout or invalid response may occur AFTER the upstream
                // reset was accepted. Withhold old credentials and prevent
                // another customer reset until the result is checked.
                DB::table('nodexa_flax_services')
                    ->where('id', $service)
                    ->where('user_id', $request->user()->id)
                    ->update([
                        'credential_reset_pending_at' => now(),
                        'credential_reset_previous_hash' => $previousHash,
                        'updated_at' => now(),
                    ]);
            }

            return redirect()->route('store.client.vps.show', $service)
                ->withErrors(['vps' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            // Even unexpected validation, database or cache errors must never
            // reveal server internals or API secrets to a customer.
            \Illuminate\Support\Facades\Log::warning('Nodexa VPS password reset internal failure', [
                'service_id' => $service,
                'reason' => 'internal_failure',
                'type' => class_basename($exception),
            ]);

            return redirect()->route('store.client.vps.show', $service)
                ->withErrors(['vps' => 'Nodexa kunne ikke færdiggøre password-handlingen. Kontakt Nodexa Support med VPS-ID ' . $service . '.']);
        } finally {
            $lock->release();
        }
    }

    /**
     * Store a customer-managed copy of their VPS login password.
     * This is NOT a provider password reset. Never store plaintext.
     */
    public function savePassword(Request $request, int $service): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $owned = $this->ownedPasswordService($request, $service);

        DB::table('nodexa_flax_services')->where('id', $owned->id)->update([
            'credential_password_encrypted' => Crypt::encryptString($data['password']),
            'credential_reset_pending_at' => null,
            'credential_reset_previous_hash' => null,
            'updated_at' => now(),
        ]);

        return redirect()->route('store.client.vps.show', $service)
            ->with('success', 'Adgangskoden blev gemt krypteret i Nodexa. Den ændrer ikke passwordet på selve VPS’en.');
    }

    public function revealPassword(Request $request, int $service): JsonResponse
    {
        $owned = $this->ownedPasswordService($request, $service);
        if (!$this->credentialResetStorageReady()) {
            return response()->json([
                'message' => 'Passwordfunktionen mangler en systemopdatering. Kontakt Nodexa Support.',
            ], 503)->header('Cache-Control', 'private, no-store, max-age=0');
        }
        $userId = (int) $request->user()->id;

        // Fetch the latest stored password on demand. Never preload a secret
        // into HTML or cache it in the browser.
        $remote = null;
        $remoteUnavailable = false;
        $lookupError = null;

        try {
            $remote = $this->flax->getStoredOsPassword($service, $userId);
        } catch (\Pterodactyl\Services\Nodexa\VpsCredentialLookupException $exception) {
            $remoteUnavailable = true;
            $lookupError = $exception->getMessage();

            // Log only a safe category, never the provider response, key or
            // password. Admins can now see why automatic retrieval failed.
            \Illuminate\Support\Facades\Log::warning('Nodexa VPS password lookup failed', [
                'service_id' => $service,
                'reason' => $exception->reason,
            ]);
        } catch (\Throwable $exception) {
            $remoteUnavailable = true;
            $lookupError = 'VPS-password kunne ikke hentes på grund af en intern fejl. Kontakt Nodexa Support.';
            \Illuminate\Support\Facades\Log::warning('Nodexa VPS password lookup exception', [
                'service_id' => $service,
                'reason' => 'unexpected_failure',
            ]);
        }

        if (is_string($remote) && $remote !== '') {
            $decision = DB::transaction(function () use ($service, $userId, $remote) {
                // Re-read under a row lock: a reset may have started while
                // get_password was in flight. Never overwrite a pending reset
                // with an earlier/stale API response.
                $current = DB::table('nodexa_flax_services')
                    ->where('id', $service)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$current || !in_array($current->status, ['active', 'suspended'], true)) {
                    return 'missing';
                }

                if (!empty($current->credential_reset_pending_at)
                    && !empty($current->credential_reset_previous_hash)) {
                    $currentHash = hash_hmac('sha256', $remote, (string) config('app.key'));
                    if (hash_equals($current->credential_reset_previous_hash, $currentHash)) {
                        return 'pending';
                    }
                }

                DB::table('nodexa_flax_services')
                    ->where('id', $service)
                    ->where('user_id', $userId)
                    ->update([
                        'credential_password_encrypted' => Crypt::encryptString($remote),
                        'credential_reset_pending_at' => null,
                        'credential_reset_previous_hash' => null,
                        'updated_at' => now(),
                    ]);

                return 'ready';
            }, 3);

            if ($decision === 'missing') {
                return response()->json(['message' => 'Denne VPS er ikke længere tilgængelig.'], 404)
                    ->header('Cache-Control', 'private, no-store, max-age=0');
            }

            if ($decision === 'pending') {
                return response()->json([
                    'message' => 'Nulstillingen afventer stadig et nyt password. Prøv at hente det igen om lidt.',
                    'pending' => true,
                ], 409)->header('Cache-Control', 'private, no-store, max-age=0')
                    ->header('X-Content-Type-Options', 'nosniff');
            }

            return response()->json([
                'password' => $remote,
                'source' => 'provider',
                'message' => 'Senest gemte VPS-password blev hentet automatisk.',
            ])->header('Cache-Control', 'private, no-store, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('X-Content-Type-Options', 'nosniff');
        }

        if (!empty($owned->credential_reset_pending_at)) {
            return response()->json([
                'message' => 'Det nye password er endnu ikke tilgængeligt. Prøv igen om lidt.',
                'pending' => true,
            ], 409)->header('Cache-Control', 'private, no-store, max-age=0')
                ->header('X-Content-Type-Options', 'nosniff');
        }

        // If the provider has no known credential (or the API temporarily
        // fails), an old manually saved copy can still be shown, but it must
        // be clearly labeled as NOT verified against the live VPS login.
        if (!empty($owned->credential_password_encrypted)) {
            try {
                $saved = Crypt::decryptString($owned->credential_password_encrypted);

                return response()->json([
                    'password' => $saved,
                    'source' => 'local',
                    'message' => $remoteUnavailable
                        ? 'Viser en tidligere gemt kopi. ' . ($lookupError ?? 'Det aktuelle password kunne ikke kontrolleres.')
                        : 'Intet password er gemt automatisk. Viser en tidligere gemt kopi.',
                ])->header('Cache-Control', 'private, no-store, max-age=0')
                    ->header('Pragma', 'no-cache')
                    ->header('X-Content-Type-Options', 'nosniff');
            } catch (\Throwable $exception) {
                return response()->json([
                    'message' => 'Den gemte adgangskode kunne ikke læses. Kontakt Nodexa Support.',
                ], 500)->header('Cache-Control', 'private, no-store, max-age=0');
            }
        }

        return response()->json([
            'message' => $remoteUnavailable
                ? ($lookupError ?? 'Password kunne ikke hentes automatisk. Kontakt Nodexa Support.')
                : 'Der er endnu ikke gemt et VPS-password. Kontakt Nodexa Support, hvis du mangler adgang.',
        ], $remoteUnavailable ? 503 : 404)->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function deletePassword(Request $request, int $service): RedirectResponse
    {
        $owned = $this->ownedPasswordService($request, $service);

        DB::table('nodexa_flax_services')->where('id', $owned->id)->update([
            'credential_password_encrypted' => null,
            'updated_at' => now(),
        ]);

        return redirect()->route('store.client.vps.show', $service)
            ->with('success', 'Den gemte adgangskode blev fjernet fra Nodexa. VPS’ens faktiske login er uændret.');
    }

    private function credentialResetStorageReady(): bool
    {
        return Schema::hasColumn('nodexa_flax_services', 'credential_reset_pending_at')
            && Schema::hasColumn('nodexa_flax_services', 'credential_reset_previous_hash');
    }

    private function ownedPasswordService(Request $request, int $service): object
    {
        abort_unless(Schema::hasColumn('nodexa_flax_services', 'credential_password_encrypted'), 503);

        $owned = DB::table('nodexa_flax_services')
            ->where('id', $service)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($owned, 404);
        abort_unless($owned->upstream_server_id
            && in_array($owned->status, ['active', 'suspended'], true), 403);

        return $owned;
    }

    public function action(Request $request, int $service): RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:boot,shutdown,restart,poweroff,refresh',
        ]);

        $owned = DB::table('nodexa_flax_services')
            ->where('id', $service)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($owned, 404);

        try {
            if ($data['action'] === 'refresh') {
                $this->flax->syncService($service);
            } else {
                $this->flax->power($service, $request->user()->id, $data['action']);
            }
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['vps' => \Pterodactyl\Services\Nodexa\CustomerBranding::serviceError()]);
        }

        return redirect()->route('store.client.vps.show', $service)
            ->with('success', $data['action'] === 'refresh'
                ? 'Status for denne VPS er opdateret.'
                : 'VPS-handlingen er sendt til behandling.');
    }
}
