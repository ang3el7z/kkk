<?php

declare(strict_types=1);

namespace VpnBot\Module\Dnstt;

use VpnBot\Infrastructure\Runtime\ContainerShell;

final class SshDnsttRuntime implements DnsttRuntime
{
    public function __construct(
        private readonly ContainerShell $shell,
    ) {
    }

    public function stop(): string
    {
        return $this->shell->exec('pkill dnstt', 'dnstt');
    }

    public function ensureUserPassword(string $username, string $password): string
    {
        $this->shell->exec("adduser -D -s /bin/sh $username", 'dnstt');

        return $this->shell->exec("echo '$username:$password' | chpasswd", 'dnstt');
    }

    public function generateKeyPair(): string
    {
        return $this->shell->exec('dnstt-server -gen-key -privkey-file /dnstt/server.key -pubkey-file /dnstt/server.pub', 'dnstt');
    }

    public function start(string $domain): string
    {
        return $this->shell->exec("dnstt-server -udp :53 -privkey-file /dnstt/server.key $domain 127.0.0.1:22", 'dnstt', false, '/logs/dnstt');
    }
}
