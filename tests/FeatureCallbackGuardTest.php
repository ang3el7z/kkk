<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureDefinition.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRegistry.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/SqliteFeatureRepository.php';
    require dirname(__DIR__) . '/src/Telegram/FeatureCallbackGuard.php';
}

use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteFeatureRepository;
use VpnBot\Telegram\FeatureCallbackGuard;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-feature-callback-guard-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$registry = new FeatureRegistry();
$connection = (new ConnectionFactory())->create($databasePath);
$migrator = new Migrator($connection, dirname(__DIR__) . '/database/migrations');
$migrator->migrate();

$repository = new SqliteFeatureRepository($connection, $registry);
$repository->setEnabled('xray', false);

$guard = new FeatureCallbackGuard($registry, $repository);

assertFeatureGuard($guard->isAllowed('/xray') === false, '/xray must be blocked when xray feature is disabled');
assertFeatureGuard($guard->isAllowed('/menu config') === true, '/menu config must stay allowed');
assertFeatureGuard($guard->isAllowed('/restart') === true, '/restart must stay allowed');

$connection = null;

@unlink($databasePath);

echo "FeatureCallbackGuardTest: OK\n";

function assertFeatureGuard(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
