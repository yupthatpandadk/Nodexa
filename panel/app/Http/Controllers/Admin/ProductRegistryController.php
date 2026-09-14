<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Http\Controllers\Controller;

class ProductRegistryController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureRegistry();
        $this->syncRegistry();

        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $type = trim((string) $request->input('type', ''));

        $query = DB::table('nodexa_product_registry as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->select('r.*', 'u.username', 'u.email', 'u.name_first', 'u.name_last');

        if ($type !== '') $query->where('r.product_type', $type);
        if ($status !== '') $query->where('r.status', $status);
        if ($search !== '') {
            $needle = '%' . $search . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('r.product_code', 'like', $needle)
                    ->orWhere('r.source_id', 'like', $needle)
                    ->orWhere('r.external_id', 'like', $needle)
                    ->orWhere('r.name', 'like', $needle)
                    ->orWhere('u.username', 'like', $needle)
                    ->orWhere('u.email', 'like', $needle)
                    ->orWhere('u.name_first', 'like', $needle)
                    ->orWhere('u.name_last', 'like', $needle)
                    ->orWhereRaw("CONCAT(COALESCE(u.name_first,''),' ',COALESCE(u.name_last,'')) LIKE ?", [$needle]);
            });
        }

        $products = $query->orderByDesc('r.id')->paginate(100)->appends($request->query());
        $stats = [
            'total' => DB::table('nodexa_product_registry')->count(),
            'active' => DB::table('nodexa_product_registry')->whereNotIn('status', ['cancelled','terminated','deleted'])->count(),
            'servers' => DB::table('nodexa_product_registry')->where('product_type', 'server')->count(),
            'vps' => DB::table('nodexa_product_registry')->where('product_type', 'vps')->count(),
        ];

        return view('admin.products.index', compact('products', 'stats', 'search', 'status', 'type'));
    }

    private function ensureRegistry(): void
    {
        if (Schema::hasTable('nodexa_product_registry')) return;
        Schema::create('nodexa_product_registry', function ($table) {
            $table->bigIncrements('id');
            $table->string('product_code', 32)->unique();
            $table->string('product_type', 20)->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('external_id', 191)->nullable()->index();
            $table->string('name', 191)->nullable();
            $table->string('status', 64)->nullable()->index();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['product_type', 'source_id']);
        });
    }

    private function syncRegistry(): void
    {
        if (Schema::hasTable('servers')) {
            DB::table('servers')->orderBy('id')->chunkById(250, function ($servers) {
                foreach ($servers as $server) {
                    $this->upsertProduct('server', (int) $server->id, (int) $server->owner_id, $server->uuid ?? $server->external_id ?? null, $server->name ?? null, $server->status ?: 'active', $server->created_at ?? now());
                }
            });
        }
        if (Schema::hasTable('vps_services')) {
            DB::table('vps_services')->orderBy('id')->chunkById(250, function ($services) {
                foreach ($services as $service) {
                    $external = $service->provider_server_id ?? $service->server_id ?? null;
                    $name = $service->hostname ?? ('VPS #' . $service->id);
                    $this->upsertProduct('vps', (int) $service->id, (int) ($service->user_id ?? 0), $external, $name, $service->status ?? 'unknown', $service->created_at ?? now());
                }
            });
        }
    }

    private function upsertProduct(string $type, int $sourceId, int $userId, $externalId, $name, $status, $createdAt): void
    {
        $existing = DB::table('nodexa_product_registry')->where('product_type', $type)->where('source_id', $sourceId)->first();
        $ended = in_array((string) $status, ['cancelled','terminated','deleted'], true) ? now() : null;
        $data = ['user_id' => $userId ?: null, 'external_id' => $externalId, 'name' => $name, 'status' => $status, 'ended_at' => $ended, 'updated_at' => now()];
        if ($existing) {
            DB::table('nodexa_product_registry')->where('id', $existing->id)->update($data);
            return;
        }
        DB::table('nodexa_product_registry')->insert(array_merge($data, [
            'product_code' => $this->generateCode($type), 'product_type' => $type, 'source_id' => $sourceId,
            'purchased_at' => $createdAt, 'created_at' => now(),
        ]));
    }

    private function generateCode(string $type): string
    {
        $prefix = $type === 'vps' ? 'VPS-' : 'SRV-';
        do { $code = $prefix . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8)); }
        while (DB::table('nodexa_product_registry')->where('product_code', $code)->exists());
        return $code;
    }
}
