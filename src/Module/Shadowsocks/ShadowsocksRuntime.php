<?php
declare(strict_types=1);

namespace VpnBot\Module\Shadowsocks;

interface ShadowsocksRuntime
{
    public function startServer(): string;

    public function stopServer(): string;

    public function startLocal(): string;

    public function stopLocal(): string;
}
