<?php

namespace Pterodactyl\Services\Store;

use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Services\Servers\ServerCreationService;

class ProvisionStoreOrderService
{
    public function __construct(private ServerCreationService $servers) {}

    public function handle(StoreOrder $order)
    {
        if ($order->server_id) {
            return $order->server;
        }

        $order->loadMissing(['product.egg.variables', 'user']);
        $product = $order->product;
        $egg = $product->egg;

        $allocation = Allocation::query()->whereNull('server_id')->orderBy('node_id')->orderBy('id')->first();
        if (!$allocation) {
            throw new \RuntimeException('Ingen ledige allocations er tilgængelige.');
        }

        $environment = [];
        foreach ($egg->variables as $variable) {
            $environment[$variable->env_variable] = $variable->default_value;
        }

        $server = $this->servers->handle([
            'name' => $order->server_name ?: ($product->name . ' #' . $order->id),
            'description' => 'Bestilt via Nodexa Storefront · ordre #' . $order->id,
            'owner_id' => $order->user_id,
            'egg_id' => $egg->id,
            'allocation_id' => $allocation->id,
            'memory' => $product->memory,
            'swap' => 0,
            'disk' => $product->disk,
            'io' => 500,
            'cpu' => $product->cpu,
            'oom_disabled' => true,
            'database_limit' => $product->databases,
            'allocation_limit' => $product->allocations,
            'backup_limit' => $product->backups,
            'startup' => $egg->startup,
            'image' => array_values($egg->docker_images ?: [])[0] ?? null,
            'environment' => $environment,
            'start_on_completion' => true,
        ]);

        $order->forceFill(['server_id' => $server->id, 'status' => 'active', 'provisioned_at' => now()])->save();

        return $server;
    }
}
