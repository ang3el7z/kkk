<?php

declare(strict_types=1);

namespace VpnBot\Application\Feature;

use RuntimeException;
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

    public function status(array $services): array
    {
        $states = [];

        foreach ($services as $service) {
            $states[$service] = 'unknown';
        }

        if ($services === []) {
            return $states;
        }

        try {
            $output = trim($this->capture(
                array_merge($this->composeBaseCommand, ['ps', '--all', '--format', 'json'], $services)
            ));
        } catch (\Throwable) {
            return $states;
        }

        if ($output === '') {
            foreach ($services as $service) {
                $states[$service] = 'missing';
            }

            return $states;
        }

        $rows = $this->decodeStatusRows($output);

        if ($rows === []) {
            return $states;
        }

        foreach ($rows as $row) {
            $service = isset($row['Service']) ? (string) $row['Service'] : null;

            if ($service === null || ! array_key_exists($service, $states)) {
                continue;
            }

            $states[$service] = $this->normalizeStatusRow($row);
        }

        foreach ($services as $service) {
            if ($states[$service] === 'unknown' && ! $this->hasServiceRow($rows, $service)) {
                $states[$service] = 'missing';
            }
        }

        return $states;
    }

    /**
     * @param list<string> $command
     */
    private function capture(array $command): string
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
            $this->workingDirectory
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
            throw new RuntimeException(trim($stderr !== '' ? $stderr : $stdout));
        }

        return (string) $stdout;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeStatusRows(string $output): array
    {
        $decoded = json_decode($output, true);

        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                return array_values(array_filter($decoded, 'is_array'));
            }

            if ($decoded !== []) {
                return [$decoded];
            }
        }

        $rows = [];

        foreach (preg_split('~\R+~', $output) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);

            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeStatusRow(array $row): string
    {
        $state = strtolower((string) ($row['State'] ?? ''));
        $status = strtolower((string) ($row['Status'] ?? ''));
        $candidate = $state !== '' ? $state : $status;

        if ($candidate === '') {
            return 'unknown';
        }

        if (str_contains($candidate, 'running')) {
            return 'running';
        }

        if (
            str_contains($candidate, 'exited')
            || str_contains($candidate, 'stopped')
            || str_contains($candidate, 'dead')
            || str_contains($candidate, 'created')
            || str_contains($candidate, 'restarting')
        ) {
            return 'stopped';
        }

        return 'unknown';
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function hasServiceRow(array $rows, string $service): bool
    {
        foreach ($rows as $row) {
            if (($row['Service'] ?? null) === $service) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $command
     */
    private function buildCommandLine(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }
}
