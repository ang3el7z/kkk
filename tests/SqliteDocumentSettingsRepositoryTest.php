<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Settings/SettingsRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/SqliteDocumentSettingsRepository.php';
}

use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteDocumentSettingsRepository;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-document-settings-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$connection = (new ConnectionFactory())->create($databasePath);
(new Migrator($connection, dirname(__DIR__) . '/database/migrations'))->migrate();

$repository = new SqliteDocumentSettingsRepository($connection, 'legacy.pac', ['transport' => 'Websocket']);
assertDocumentSettings($repository->all()['transport'] === 'Websocket', 'document repo must expose defaults before first write');

$repository->set('domain', 'example.com');
$repository->set('limitpage', 25);

assertDocumentSettings($repository->get('domain') === 'example.com', 'document repo must persist scalar values');
assertDocumentSettings($repository->all()['transport'] === 'Websocket', 'document repo must preserve defaults after partial writes');

$repository->replaceAll(['transport' => 'Reality', 'domain' => 'vpn.example.com']);
assertDocumentSettings($repository->all() === ['transport' => 'Reality', 'domain' => 'vpn.example.com'], 'replaceAll must replace stored document');

$connection = null;
@unlink($databasePath);

echo "SqliteDocumentSettingsRepositoryTest: OK\n";

function assertDocumentSettings(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
