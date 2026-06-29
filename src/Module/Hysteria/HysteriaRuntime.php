<?php
declare(strict_types=1);

namespace VpnBot\Module\Hysteria;

interface HysteriaRuntime
{
    public function start(): string;

    public function stop(): string;
}
