<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Bootstrap/DatabaseBootstrapper.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureDefinition.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRegistry.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/SqliteFeatureRepository.php';
}

use VpnBot\Bootstrap\DatabaseBootstrapper;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Database\ConnectionFactory;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-bootstrap-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$bootstrapper = new DatabaseBootstrapper(
    new ConnectionFactory(),
    new FeatureRegistry(),
    $databasePath,
    dirname(__DIR__) . '/database/migrations'
);

$connection = $bootstrapper->bootstrap();

assertDatabaseBootstrapper(file_exists($databasePath), 'Bootstrap must create SQLite file when it is missing');

$tables = $connection->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
assertDatabaseBootstrapper(in_array('features', $tables, true), 'Bootstrap must create features table');
assertDatabaseBootstrapper(in_array('settings', $tables, true), 'Bootstrap must create schema migrations tables');

$featureCount = (int) $connection->query('SELECT COUNT(*) FROM features')->fetchColumn();
assertDatabaseBootstrapper($featureCount > 0, 'Bootstrap must seed feature defaults');

$xrayEnabled = (int) $connection->query("SELECT enabled FROM features WHERE id = 'xray'")->fetchColumn();
assertDatabaseBootstrapper($xrayEnabled === 1, 'Bootstrap must seed enabled-by-default feature states');

$connection = null;
@unlink($databasePath);

echo "DatabaseBootstrapperTest: OK\n";

function assertDatabaseBootstrapper(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
