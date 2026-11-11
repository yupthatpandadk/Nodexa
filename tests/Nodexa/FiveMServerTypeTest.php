<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\FiveMServerType;

class FiveMServerTypeTest extends TestCase
{
    public function test_fivem_nests_use_fivem_panel(): void
    {
        foreach (['FiveM', 'fivem', 'FXServer', 'GTA V', 'Five M'] as $name) {
            $this->assertTrue(FiveMServerType::matches($name), $name);
        }
    }

    public function test_other_nests_keep_standard_panel(): void
    {
        foreach (['Minecraft', 'Fabric', 'Paper', 'Rust', 'Ark', 'FiveM Resources', 'notfivem', '', null] as $name) {
            $this->assertFalse(FiveMServerType::matches($name), (string) $name);
        }
    }
}
