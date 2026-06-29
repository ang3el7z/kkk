<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
}

use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;

$options = getopt('', ['db:', 'migrations:']);

$databasePath = $options['db'] ?? '/data/vpnbot.sqlite';
$migrationsDirectory = $options['migrations'] ?? dirname(__DIR__) . '/database/migrations';

if (! is_string($databasePath) || $databasePath === '') {
    throw new InvalidArgumentException('The --db option must be a non-empty string.');
}

if (! is_string($migrationsDirectory) || $migrationsDirectory === '') {
    throw new InvalidArgumentException('The --migrations option must be a non-empty string.');
}

$connection = (new ConnectionFactory())->create($databasePath);
$migrator = new Migrator($connection, $migrationsDirectory);
$appliedMigrations = $migrator->migrate();

echo sprintf("Database: %s\n", $databasePath);

if ($appliedMigrations === []) {
    echo "No new migrations.\n";

    return;
}

echo "Applied migrations:\n";

foreach ($appliedMigrations as $migrationName) {
    echo sprintf("- %s\n", $migrationName);
}
