<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Database;

use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly PDO $connection,
        private readonly string $migrationsDirectory,
    ) {
    }

    public function migrate(): array
    {
        if (! is_dir($this->migrationsDirectory)) {
            throw new RuntimeException(sprintf('Migrations directory not found: %s', $this->migrationsDirectory));
        }

        $this->ensureMigrationsTable();

        $appliedMigrations = [];
        $migrationFiles = glob($this->migrationsDirectory . DIRECTORY_SEPARATOR . '*.sql');

        if ($migrationFiles === false) {
            throw new RuntimeException(sprintf('Failed to read migrations from: %s', $this->migrationsDirectory));
        }

        sort($migrationFiles, SORT_STRING);

        foreach ($migrationFiles as $migrationFile) {
            $migrationName = basename($migrationFile);

            if ($this->hasMigration($migrationName)) {
                continue;
            }

            $sql = file_get_contents($migrationFile);

            if ($sql === false) {
                throw new RuntimeException(sprintf('Failed to read migration file: %s', $migrationFile));
            }

            $this->connection->beginTransaction();

            try {
                $this->connection->exec($sql);
                $this->recordMigration($migrationName);
                $this->connection->commit();
            } catch (\Throwable $exception) {
                $this->connection->rollBack();

                throw $exception;
            }

            $appliedMigrations[] = $migrationName;
        }

        return $appliedMigrations;
    }

    private function ensureMigrationsTable(): void
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                name TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
    }

    private function hasMigration(string $migrationName): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM migrations WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $migrationName]);

        return $statement->fetchColumn() !== false;
    }

    private function recordMigration(string $migrationName): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO migrations (name, applied_at) VALUES (:name, :applied_at)'
        );

        $statement->execute([
            'name' => $migrationName,
            'applied_at' => gmdate('c'),
        ]);
    }
}
