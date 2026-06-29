<?php

declare(strict_types=1);

namespace VpnBot\Module\AdGuard;

interface AdGuardRuntime
{
    public function start(): string;

    public function stop(): string;
}
