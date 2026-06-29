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
    require dirname(__DIR__) . '/src/Telegram/Menu/MenuFilter.php';
}

use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteFeatureRepository;
use VpnBot\Telegram\Menu\MenuFilter;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-menu-filter-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$registry = new FeatureRegistry();
$connection = (new ConnectionFactory())->create($databasePath);
$migrator = new Migrator($connection, dirname(__DIR__) . '/database/migrations');
$migrator->migrate();

$repository = new SqliteFeatureRepository($connection, $registry);
$repository->setEnabled('xray', false);
$repository->setEnabled('adguard', false);

$filter = new MenuFilter($registry, $repository);
$keyboard = [
    [
        ['text' => 'Xray', 'callback_data' => '/xray'],
        ['text' => 'Naive', 'callback_data' => '/menu naive'],
    ],
    [
        ['text' => 'AdGuard', 'callback_data' => '/menu adguard'],
        ['text' => 'Config', 'callback_data' => '/menu config'],
    ],
];

$filteredKeyboard = $filter->filter($keyboard);
$callbacks = [];

foreach ($filteredKeyboard as $row) {
    foreach ($row as $button) {
        $callbacks[] = $button['callback_data'] ?? null;
    }
}

assertMenuFilter(! in_array('/xray', $callbacks, true), 'disabled xray button must be removed');
assertMenuFilter(! in_array('/menu adguard', $callbacks, true), 'disabled adguard button must be removed');
assertMenuFilter(in_array('/menu naive', $callbacks, true), 'enabled naive button must stay');
assertMenuFilter(in_array('/menu config', $callbacks, true), 'non-feature config button must stay');

$connection = null;

@unlink($databasePath);

echo "MenuFilterTest: OK\n";

function assertMenuFilter(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
