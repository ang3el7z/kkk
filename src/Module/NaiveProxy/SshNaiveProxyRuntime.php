<?php

declare(strict_types=1);

namespace VpnBot\Module\NaiveProxy;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshNaiveProxyRuntime implements NaiveProxyRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function start(): string
    {
        return $this->shell->exec('caddy run -c /config/Caddyfile', 'np', false);
    }

    public function stop(): string
    {
        return $this->shell->exec('pkill caddy', 'np');
    }
}
