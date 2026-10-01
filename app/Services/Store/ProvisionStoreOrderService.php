<?php

namespace Pterodactyl\Services\Store;

use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Nodexa\NodexaEventService;

class ProvisionStoreOrderService
{
    public function __construct(
        private ServerCreationService $servers,
        private NodexaEventService $events,
    ) {}

    public function handle(StoreOrder $order)
    {
        if ($order->server_id) {
            return $order->server;
        }

        $order->loadMissing(['product.egg.variables', 'user']);
        $product = $order->product;
        $egg = $product->egg;

        // Pick the healthiest available node with enough remaining RAM/disk and
        // the lowest combined utilization instead of blindly taking the first allocation.
        $candidates = Node::query()
            ->where('public', true)
            ->where('maintenance_mode', false)
            ->orderBy('id')
            ->get()
            ->map(function (Node $node) use ($product) {
                $usedMemory = (int) $node->servers()->sum('memory');
                $usedDisk = (int) $node->servers()->sum('disk');
                $memoryLimit = (float) $node->memory * (1 + ((float) $node->memory_overallocate / 100));
                $diskLimit = (float) $node->disk * (1 + ((float) $node->disk_overallocate / 100));
                $hasAllocation = Allocation::query()->where('node_id', $node->id)->whereNull('server_id')->exists();

                $viable = $hasAllocation
                    && ($usedMemory + (int) $product->memory) <= $memoryLimit
                    && ($usedDisk + (int) $product->disk) <= $diskLimit;

                $memoryRatio = $memoryLimit > 0 ? $usedMemory / $memoryLimit : 1;
                $diskRatio = $diskLimit > 0 ? $usedDisk / $diskLimit : 1;

                return [
                    'node' => $node,
                    'viable' => $viable,
                    'score' => $memoryRatio + $diskRatio,
                ];
            })
            ->filter(fn (array $candidate) => $candidate['viable'])
            ->sortBy('score')
            ->values();

        $node = $candidates->first()['node'] ?? null;
        $allocation = $node
            ? Allocation::query()->where('node_id', $node->id)->whereNull('server_id')->orderBy('id')->first()
            : null;

        if (!$allocation) {
            throw new \RuntimeException('Ingen node har nok ledige ressourcer og allocations til denne server.');
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

        $this->events->notify(
            $order->user_id,
            'Din server er klar',
            $server->name . ' er provisioneret på ' . ($server->node?->name ?? 'Nodexa') . '.',
            'server',
            '/server/' . $server->uuidShort
        );
        $this->events->emit('server.provisioned', [
            'order_id' => $order->id,
            'server_id' => $server->id,
            'server_uuid' => $server->uuid,
            'node_id' => $server->node_id,
        ], $order->user_id);

        return $server;
    }
}
