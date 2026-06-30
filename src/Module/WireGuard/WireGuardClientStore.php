<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

interface WireGuardClientStore
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function readAll(): array;

    /**
     * @param array<int, array<string, mixed>> $clients
     */
    public function saveAll(array $clients): void;
}
