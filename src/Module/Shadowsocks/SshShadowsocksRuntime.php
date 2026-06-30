<?php

declare(strict_types=1);

namespace VpnBot\Module\Shadowsocks;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshShadowsocksRuntime implements ShadowsocksRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function startServer(): string
    {
        return $this->shell->exec('ssserver -v -d -c /config.json', 'ss');
    }

    public function stopServer(): string
    {
        return $this->shell->exec('pkill ssserver', 'ss');
    }

    public function startLocal(): string
    {
        return $this->shell->exec('sslocal -v -d -c /config.json', 'proxy');
    }

    public function stopLocal(): string
    {
        return $this->shell->exec('pkill sslocal', 'proxy');
    }
}
