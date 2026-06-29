<?php

declare(strict_types=1);

namespace VpnBot\Bootstrap;

use PDO;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteFeatureRepository;

final class DatabaseBootstrapper
{
    public function __construct(
        private readonly ConnectionFactory $connectionFactory,
        private readonly FeatureRegistry $featureRegistry,
        private readonly string $databasePath = '/data/vpnbot.sqlite',
        private readonly string $migrationsDirectory = __DIR__ . '/../../database/migrations',
    ) {
    }

    public function bootstrap(): PDO
    {
        $connection = $this->connectionFactory->create($this->databasePath);
        $migrator = new Migrator($connection, $this->migrationsDirectory);
        $migrator->migrate();

        (new SqliteFeatureRepository($connection, $this->featureRegistry))->all();

        return $connection;
    }
}
