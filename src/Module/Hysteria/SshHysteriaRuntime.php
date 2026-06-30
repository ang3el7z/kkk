<?php

declare(strict_types=1);

namespace VpnBot\Module\Hysteria;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshHysteriaRuntime implements HysteriaRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function start(): string
    {
        return $this->shell->exec('hysteria server -c /config/hysteria.yaml', 'hy', false, '/logs/hysteria');
    }

    public function stop(): string
    {
        return $this->shell->exec('pkill hysteria', 'hy');
    }
}
