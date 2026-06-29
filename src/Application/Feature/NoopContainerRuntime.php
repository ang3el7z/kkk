<?php

declare(strict_types=1);

namespace VpnBot\Application\Feature;

final class NoopContainerRuntime implements ContainerRuntime
{
    public function start(array $services): void
    {
    }

    public function stopAndRemove(array $services): void
    {
    }
}
