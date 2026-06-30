<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Database;

use PDO;
use VpnBot\Domain\Settings\SettingsRepository;

final class SqliteDocumentSettingsRepository implements SettingsRepository
{
    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly string $documentKey,
        private readonly array $defaults = [],
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $settings = $this->all();
        $settings[$key] = $value;
        $this->replaceAll($settings);
    }

    public function all(): array
    {
        $statement = $this->connection->prepare('SELECT value_json FROM settings WHERE key = :key LIMIT 1');
        $statement->execute(['key' => $this->documentKey]);
        $value = $statement->fetchColumn();

        if (! is_string($value) || $value === '') {
            return $this->defaults;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_replace($this->defaults, $decoded) : $this->defaults;
    }

    public function replaceAll(array $settings): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO settings (key, value_json, updated_at) VALUES (:key, :value_json, :updated_at)
             ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at'
        );

        $statement->execute([
            'key' => $this->documentKey,
            'value_json' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'updated_at' => gmdate('c'),
        ]);
    }
}
