<?php

declare(strict_types=1);

namespace VpnBot\Module\Mtproto;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshMtprotoRuntime implements MtprotoRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function stop(): string
    {
        return $this->shell->exec('pkill mtproto-proxy', 'tg');
    }

    public function start(string $command): string
    {
        return $this->shell->exec($command, 'tg', false, '/logs/mtproto');
    }

    public function isRunning(): bool
    {
        return (bool) $this->shell->exec('pgrep mtproto-proxy', 'tg');
    }
}
