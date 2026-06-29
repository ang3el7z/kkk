<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

use RuntimeException;

final class LegacyWireGuardClientStore
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readAll(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read WireGuard clients: %s', $this->path));
        }

        $decoded = json_decode($contents, true);

        if ($decoded === null && trim($contents) !== 'null' && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Failed to decode WireGuard clients: %s', $this->path));
        }

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     */
    public function saveAll(array $clients): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create WireGuard client directory: %s', $directory));
        }

        $encoded = json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode WireGuard clients.');
        }

        if (file_put_contents($this->path, $encoded) === false) {
            throw new RuntimeException(sprintf('Failed to write WireGuard clients: %s', $this->path));
        }
    }
}
