<?php
declare(strict_types=1);

namespace VpnBot\Module\Maintenance;

final class MaintenanceModule
{
    public function __construct(
        private readonly LogStore $logStore,
        private readonly UpdateStateStore $updateStateStore,
        private readonly MaintenanceRuntime $runtime,
    ) {
    }

    /**
     * @return list<array{name:string,size:int,path:string}>
     */
    public function logs(): array
    {
        return $this->logStore->list();
    }

    public function trackedBranch(): string
    {
        return $this->updateStateStore->trackedBranch();
    }

    public function currentBranch(): string
    {
        return $this->runtime->currentBranch();
    }

    /**
     * @return list<string>
     */
    public function branchStatusLines(): array
    {
        return $this->runtime->branchStatusLines();
    }

    /**
     * @return array{name:string,size:int,path:string}|null
     */
    public function logByIndex(int $index): ?array
    {
        $logs = $this->logs();

        return $logs[$index] ?? null;
    }

    public function clearLogByIndex(int $index): void
    {
        $log = $this->logByIndex($index);

        if ($log !== null) {
            $this->logStore->clear($log['name']);
        }
    }

    public function deleteLogByIndex(int $index): void
    {
        $log = $this->logByIndex($index);

        if ($log !== null) {
            $this->logStore->delete($log['name']);
        }
    }

    public function clearAllLogs(): void
    {
        foreach ($this->logs() as $log) {
            $this->logStore->clear($log['name']);
        }
    }

    /**
     * @return array{enabled:bool,valid:bool,display:?string}
     */
    public function describeSchedule(string $value): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('/', $value))));

        if ($parts === []) {
            return ['enabled' => false, 'valid' => true, 'display' => null];
        }

        if (count($parts) < 2 || empty(strtotime($parts[0])) || empty(strtotime($parts[1]))) {
            return ['enabled' => true, 'valid' => false, 'display' => $value];
        }

        return [
            'enabled' => true,
            'valid' => true,
            'display' => date('Y-m-d H:i', strtotime($parts[0])) . ' start / ' . $parts[1] . ' period',
        ];
    }

    public function normalizeSchedule(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $parts = array_map('trim', explode('/', $value, 2));

        if (count($parts) < 2 || empty(strtotime($parts[0])) || empty(strtotime($parts[1]))) {
            return null;
        }

        return date('Y-m-d H:i', strtotime($parts[0])) . ' / ' . $parts[1];
    }

    public function reloadMessage(): string
    {
        return $this->updateStateStore->reloadMessage();
    }

    public function updateMessage(): string
    {
        return $this->updateStateStore->message();
    }

    public function updateJsonPath(): string
    {
        return $this->updateStateStore->updateJsonPath();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function storeReloadRequest(string $reloadMessage, string $key, array $payload, string $pipe): void
    {
        $this->updateStateStore->writeReloadArtifacts($reloadMessage, $key, $payload, $pipe);
    }

    public function clearReloadState(): void
    {
        $this->updateStateStore->clearReloadArtifacts();
    }
}
