<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Database;

use PDO;
use PDOStatement;
use RuntimeException;
use VpnBot\Domain\Feature\FeatureDefinition;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Domain\Feature\FeatureRepository;

final class SqliteFeatureRepository implements FeatureRepository
{
    private bool $seeded = false;

    public function __construct(
        private readonly PDO $connection,
        private readonly FeatureRegistry $registry,
    ) {
    }

    public function isEnabled(string $featureId): bool
    {
        $this->ensureSeeded();
        $this->registry->get($featureId);

        $statement = $this->connection->prepare('SELECT enabled FROM features WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $featureId]);
        $enabled = $statement->fetchColumn();

        if ($enabled === false) {
            throw new RuntimeException(sprintf('Feature state not found: %s', $featureId));
        }

        return (bool) $enabled;
    }

    public function setEnabled(string $featureId, bool $enabled): void
    {
        $this->ensureSeeded();

        $definition = $this->registry->get($featureId);

        if (! $enabled && ! $definition->toggleable()) {
            throw new RuntimeException(sprintf('Feature "%s" cannot be disabled.', $featureId));
        }

        $statement = $this->connection->prepare(
            'UPDATE features SET enabled = :enabled, updated_at = :updated_at WHERE id = :id'
        );

        $statement->execute([
            'id' => $featureId,
            'enabled' => $enabled ? 1 : 0,
            'updated_at' => gmdate('c'),
        ]);

        if ($statement->rowCount() === 0) {
            throw new RuntimeException(sprintf('Feature state not found: %s', $featureId));
        }
    }

    public function all(): array
    {
        $this->ensureSeeded();

        $statement = $this->connection->query('SELECT id, enabled FROM features');
        $rows = $statement->fetchAll();
        $states = [];

        foreach ($rows as $row) {
            $states[(string) $row['id']] = (bool) $row['enabled'];
        }

        $orderedStates = [];

        foreach ($this->registry->all() as $definition) {
            $featureId = $definition->id();

            if (! array_key_exists($featureId, $states)) {
                throw new RuntimeException(sprintf('Feature state not found: %s', $featureId));
            }

            $orderedStates[$featureId] = $states[$featureId];
        }

        return $orderedStates;
    }

    private function ensureSeeded(): void
    {
        if ($this->seeded) {
            return;
        }

        $count = (int) $this->connection->query('SELECT COUNT(*) FROM features')->fetchColumn();

        if ($count === 0) {
            $this->seedDefaults();
        }

        $this->seeded = true;
    }

    private function seedDefaults(): void
    {
        $timestamp = gmdate('c');
        $statement = $this->connection->prepare(
            'INSERT INTO features (id, enabled, created_at, updated_at) VALUES (:id, :enabled, :created_at, :updated_at)'
        );

        $this->connection->beginTransaction();

        try {
            foreach ($this->registry->all() as $definition) {
                $this->insertDefaultFeature($statement, $definition, $timestamp);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    private function insertDefaultFeature(PDOStatement $statement, FeatureDefinition $definition, string $timestamp): void
    {
        $statement->execute([
            'id' => $definition->id(),
            'enabled' => $definition->enabledByDefault() ? 1 : 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}
