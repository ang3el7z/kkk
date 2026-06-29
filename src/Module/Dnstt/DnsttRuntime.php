<?php
declare(strict_types=1);

namespace VpnBot\Module\Dnstt;

interface DnsttRuntime
{
    public function stop(): string;

    public function ensureUserPassword(string $username, string $password): string;

    public function generateKeyPair(): string;

    public function start(string $domain): string;
}
