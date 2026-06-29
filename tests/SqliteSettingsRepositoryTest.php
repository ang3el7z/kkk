<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Settings/SettingsRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/SqliteSettingsRepository.php';
}

use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteSettingsRepository;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-settings-repository-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$connection = (new ConnectionFactory())->create($databasePath);
$migrator = new Migrator($connection, dirname(__DIR__) . '/database/migrations');
$migrator->migrate();

$repository = new SqliteSettingsRepository($connection);
$repository->set('language', 'ru');
$repository->set('routes', ['direct' => true]);

assertSettings($repository->get('language') === 'ru', 'SQLite settings repo must read scalar values');
assertSettings($repository->get('routes') === ['direct' => true], 'SQLite settings repo must read array values');

$connection = null;

@unlink($databasePath);

echo "SqliteSettingsRepositoryTest: OK\n";

function assertSettings(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
