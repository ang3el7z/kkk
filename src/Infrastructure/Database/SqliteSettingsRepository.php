<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Database;

use PDO;
use VpnBot\Domain\Settings\SettingsRepository;

final class SqliteSettingsRepository implements SettingsRepository
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $statement = $this->connection->prepare('SELECT value_json FROM settings WHERE key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();

        if ($value === false) {
            return $default;
        }

        return json_decode((string) $value, true);
    }

    public function set(string $key, mixed $value): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO settings (key, value_json, updated_at) VALUES (:key, :value_json, :updated_at)
            ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = excluded.updated_at'
        );

        $statement->execute([
            'key' => $key,
            'value_json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'updated_at' => gmdate('c'),
        ]);
    }

    public function all(): array
    {
        $statement = $this->connection->query('SELECT key, value_json FROM settings');
        $rows = $statement->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['key']] = json_decode((string) $row['value_json'], true);
        }

        return $settings;
    }

    public function replaceAll(array $settings): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->exec('DELETE FROM settings');

            foreach ($settings as $key => $value) {
                $this->set((string) $key, $value);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }
}
