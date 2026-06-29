<?php

declare(strict_types=1);

namespace VpnBot\Application\Feature;

use VpnBot\Infrastructure\Process\CommandRunner;

final class DockerContainerRuntime implements ContainerRuntime
{
    /**
     * @param list<string> $composeBaseCommand
     */
    public function __construct(
        private readonly CommandRunner $commandRunner,
        private readonly array $composeBaseCommand,
        private readonly ?string $workingDirectory = null,
    ) {
    }

    public function start(array $services): void
    {
        if ($services === []) {
            return;
        }

        $this->commandRunner->run(
            array_merge($this->composeBaseCommand, ['up', '-d', '--no-deps'], $services),
            $this->workingDirectory
        );
    }

    public function stopAndRemove(array $services): void
    {
        if ($services === []) {
            return;
        }

        $this->commandRunner->run(
            array_merge($this->composeBaseCommand, ['stop'], $services),
            $this->workingDirectory
        );
        $this->commandRunner->run(
            array_merge($this->composeBaseCommand, ['rm', '--force', '--stop'], $services),
            $this->workingDirectory
        );
    }
}
