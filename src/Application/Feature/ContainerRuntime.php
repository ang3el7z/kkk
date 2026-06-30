<?php

declare(strict_types=1);

namespace VpnBot\Application\Feature;

interface ContainerRuntime
{
    /**
     * @param list<string> $services
     */
    public function start(array $services): void;

    /**
     * @param list<string> $services
     */
    public function stopAndRemove(array $services): void;

    /**
     * @param list<string> $services
     * @return array<string, string>
     */
    public function status(array $services): array;
}
