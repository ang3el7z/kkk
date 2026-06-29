<?php

declare(strict_types=1);

namespace VpnBot\Module\NaiveProxy;

interface NaiveProxyRuntime
{
    public function start(): string;

    public function stop(): string;
}
