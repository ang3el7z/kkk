<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

interface WireGuardRuntime
{
    public function readConfig(string $service): string;

    public function readStatus(string $service, string $binary): string;

    public function applyConfig(string $service, string $downBinary, string $upBinary, string $config): bool;
}
