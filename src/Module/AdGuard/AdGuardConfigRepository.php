<?php

declare(strict_types=1);

namespace VpnBot\Module\AdGuard;

interface AdGuardConfigRepository
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array;

    /**
     * @param array<string, mixed> $config
     */
    public function save(array $config): void;
}
