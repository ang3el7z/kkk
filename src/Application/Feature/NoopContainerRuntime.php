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

    public function status(array $services): array
    {
        $states = [];

        foreach ($services as $service) {
            $states[$service] = 'unknown';
        }

        return $states;
    }
}
