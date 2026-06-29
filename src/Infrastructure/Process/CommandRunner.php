<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Process;

interface CommandRunner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, ?string $workingDirectory = null): void;
}
