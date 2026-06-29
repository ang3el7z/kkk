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
}

use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteFeatureRepository;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-feature-repository-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$connection = (new ConnectionFactory())->create($databasePath);
$migrator = new Migrator($connection, dirname(__DIR__) . '/database/migrations');
$migrator->migrate();

$repository = new SqliteFeatureRepository($connection, new FeatureRegistry());
$allFeatures = $repository->all();

assertFeature(isset($allFeatures['xray']), 'seed must create xray feature state');
assertFeature($allFeatures['xray'] === true, 'seed must enable xray by default');
assertFeature($repository->isEnabled('php') === true, 'seed must keep php enabled');

$repository->setEnabled('xray', false);
assertFeature($repository->isEnabled('xray') === false, 'xray must be disabled after update');

try {
    $repository->setEnabled('php', false);
    throw new RuntimeException('core feature disable must raise exception');
} catch (RuntimeException $exception) {
    assertFeature(
        $exception->getMessage() === 'Feature "php" cannot be disabled.',
        'core feature disable must explain why it failed'
    );
}

$repository = null;
$connection = null;

@unlink($databasePath);

echo "SqliteFeatureRepositoryTest: OK\n";

function assertFeature(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
