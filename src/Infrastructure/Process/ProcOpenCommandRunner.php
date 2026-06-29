<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Process;

use RuntimeException;

final class ProcOpenCommandRunner implements CommandRunner
{
    public function run(array $command, ?string $workingDirectory = null): void
    {
        if ($command === []) {
            throw new RuntimeException('Command must not be empty.');
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $this->buildCommandLine($command),
            $descriptorSpec,
            $pipes,
            $workingDirectory
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start process.');
        }

        try {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        } catch (\Throwable $exception) {
            proc_terminate($process);

            throw $exception;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Command failed with exit code %d: %s%s',
                $exitCode,
                implode(' ', $command),
                $stderr !== '' ? "\n" . trim($stderr) : ($stdout !== '' ? "\n" . trim($stdout) : '')
            ));
        }
    }

    /**
     * @param list<string> $command
     */
    private function buildCommandLine(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }
}
