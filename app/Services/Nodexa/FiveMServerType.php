<?php

namespace Pterodactyl\Services\Nodexa;

/**
 * FiveM panel selection is determined solely by the server's installed Nest,
 * never by an arbitrary customer-supplied game label or frontend theme.
 */
final class FiveMServerType
{
    public static function matches(?string $nestName): bool
    {
        $name = mb_strtolower(trim((string) $nestName));

        return in_array($name, [
            'fivem', 'five m', 'fxserver', 'gta v', 'gta5', 'gta 5',
        ], true);
    }
}
