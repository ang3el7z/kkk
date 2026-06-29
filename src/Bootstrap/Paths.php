<?php

declare(strict_types=1);

namespace VpnBot\Bootstrap;

final class Paths
{
    public function __construct(
        private readonly string $configDir = '/config',
        private readonly string $dataDir = '/data',
        private readonly string $logsDir = '/logs',
        private readonly string $composeDir = '/docker/compose',
    ) {
    }

    public function configDir(): string
    {
        return $this->configDir;
    }

    public function dataDir(): string
    {
        return $this->dataDir;
    }

    public function logsDir(): string
    {
        return $this->logsDir;
    }

    public function composeDir(): string
    {
        return $this->composeDir;
    }
}
