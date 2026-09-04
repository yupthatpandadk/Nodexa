<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Egg;
use Illuminate\Support\Facades\Storage;

class EggTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return Egg::RESOURCE_NAME;
    }

    public function transform(Egg $egg): array
    {
        return [
            'uuid' => $egg->uuid,
            'name' => $egg->name,
            'icon' => $egg->icon_path ? Storage::disk('public')->url($egg->icon_path) : null,
        ];
    }
}
