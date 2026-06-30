<?php
declare(strict_types=1);

namespace VpnBot\Module\Maintenance;

use RuntimeException;

final class LogStore
{
    public function __construct(
        private readonly string $logsDir = '/logs',
    ) {
    }

    /**
     * @return list<array{name:string,size:int,path:string}>
     */
    public function list(): array
    {
        $entries = scandir($this->logsDir);

        if ($entries === false) {
            throw new RuntimeException(sprintf('Failed to scan logs directory: %s', $this->logsDir));
        }

        $logs = [];

        foreach ($entries as $entry) {
            if (preg_match('~^\.~', $entry) === 1) {
                continue;
            }

            $path = $this->logsDir . '/' . $entry;
            $logs[] = [
                'name' => $entry,
                'size' => (int) filesize($path),
                'path' => $path,
            ];
        }

        return $logs;
    }

    public function clear(string $name): void
    {
        if (file_put_contents($this->logsDir . '/' . $name, '') === false) {
            throw new RuntimeException(sprintf('Failed to clear log: %s', $name));
        }
    }

    public function delete(string $name): void
    {
        $path = $this->logsDir . '/' . $name;

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
