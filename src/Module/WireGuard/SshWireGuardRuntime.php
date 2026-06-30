<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshWireGuardRuntime implements WireGuardRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function readConfig(string $service): string
    {
        return $this->shell->exec('cat /etc/wireguard/wg0.conf', $service);
    }

    public function readStatus(string $service, string $binary): string
    {
        return $this->shell->exec($binary, $service);
    }

    public function applyConfig(string $service, string $downBinary, string $upBinary, string $config): bool
    {
        $this->shell->exec("echo '$config' > /etc/wireguard/wg0.conf", $service);
        $this->shell->exec("{$downBinary}-quick down wg0", $service);
        $this->shell->exec("{$upBinary}-quick up wg0", $service);

        return true;
    }
}
