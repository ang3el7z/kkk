<?php
declare(strict_types=1);

namespace VpnBot\Module\Mtproto;

interface MtprotoRuntime
{
    public function stop(): string;

    public function start(string $command): string;

    public function isRunning(): bool;
}
