<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
    require dirname(__DIR__) . '/src/Module/WireGuard/WireGuardClientStore.php';
    require dirname(__DIR__) . '/src/Module/WireGuard/SqliteWireGuardClientStore.php';
}

use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Module\WireGuard\SqliteWireGuardClientStore;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-wireguard-store-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$connection = (new ConnectionFactory())->create($databasePath);
(new Migrator($connection, dirname(__DIR__) . '/database/migrations'))->migrate();

$wg = new SqliteWireGuardClientStore($connection, 'wg');
$wg1 = new SqliteWireGuardClientStore($connection, 'wg1');

$wg->saveAll([
    [
        'interface' => ['## name' => 'alice', 'Address' => '10.0.0.2/32'],
        'peers' => [['Endpoint' => 'vpn.example.com:51820']],
    ],
    [
        'interface' => ['## name' => 'bob', 'Address' => '10.0.0.3/32'],
        'off' => 1,
        'peers' => [['Endpoint' => 'vpn.example.com:51820']],
    ],
]);
$wg1->saveAll([
    [
        'interface' => ['## name' => 'carol', 'Address' => '10.1.0.2/32'],
        'peers' => [['Endpoint' => 'vpn2.example.com:51821']],
    ],
]);

$wgClients = $wg->readAll();
$wg1Clients = $wg1->readAll();

assertWireGuardStore(count($wgClients) === 2, 'wg store must return clients saved for primary instance');
assertWireGuardStore(count($wg1Clients) === 1, 'wg1 store must return clients saved for secondary instance');
assertWireGuardStore(($wgClients[1]['off'] ?? null) === 1, 'wg store must preserve client payload');

$instanceRows = $connection->query('SELECT service FROM wireguard_instances ORDER BY service ASC')->fetchAll(PDO::FETCH_COLUMN);
assertWireGuardStore($instanceRows === ['wg', 'wg1'], 'store must auto-create required wireguard instances');

$connection = null;
@unlink($databasePath);

echo "SqliteWireGuardClientStoreTest: OK\n";

function assertWireGuardStore(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
