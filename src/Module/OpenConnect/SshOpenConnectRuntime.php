<?php

declare(strict_types=1);

namespace VpnBot\Module\OpenConnect;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshOpenConnectRuntime implements OpenConnectRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function start(): string
    {
        return $this->shell->exec('ocserv -c /etc/ocserv/ocserv.conf', 'oc');
    }

    public function stop(): string
    {
        return $this->shell->exec('pkill ocserv', 'oc');
    }

    public function setUserPassword(string $username, string $password): string
    {
        return $this->shell->exec("echo '$password' | ocpasswd -c /etc/ocserv/ocserv.passwd $username", 'oc');
    }

    public function deleteUser(string $username): string
    {
        return $this->shell->exec("ocpasswd -c /etc/ocserv/ocserv.passwd -d $username", 'oc');
    }
}
