<?php

declare(strict_types=1);

namespace VpnBot\Module\AdGuard;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshAdGuardRuntime implements AdGuardRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function start(): string
    {
        return $this->shell->exec('/opt/adguardhome/AdGuardHome --no-check-update --pidfile /opt/adguardhome/pid -c /config/AdGuardHome.yaml -h 0.0.0.0 -w /opt/adguardhome/work', 'ad', false);
    }

    public function stop(): string
    {
        return $this->shell->exec('kill -15 $(cat /opt/adguardhome/pid)', 'ad');
    }
}
