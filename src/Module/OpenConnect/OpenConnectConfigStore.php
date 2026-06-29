<?php

declare(strict_types=1);

namespace VpnBot\Module\OpenConnect;

use RuntimeException;

final class OpenConnectConfigStore
{
    public function __construct(
        private readonly string $configPath = '/config/ocserv.conf',
        private readonly string $passwdPath = '/config/ocserv.passwd',
    ) {
    }

    public function loadConfig(): string
    {
        $contents = file_get_contents($this->configPath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read OpenConnect config: %s', $this->configPath));
        }

        return $contents;
    }

    public function saveConfig(string $config): void
    {
        if (file_put_contents($this->configPath, $config) === false) {
            throw new RuntimeException(sprintf('Failed to write OpenConnect config: %s', $this->configPath));
        }
    }

    /**
     * @return list<string>
     */
    public function loadUsers(): array
    {
        $contents = file_get_contents($this->passwdPath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read OpenConnect passwd file: %s', $this->passwdPath));
        }

        $lines = array_filter(explode("\n", $contents), static fn (string $line): bool => trim($line) !== '');

        return array_values(array_map(
            static fn (string $line): string => explode(':', $line)[0],
            $lines
        ));
    }
}
