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
    require dirname(__DIR__) . '/src/Infrastructure/Legacy/LegacyImporter.php';
}

use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Legacy\LegacyImporter;

$options = getopt('', ['from::', 'db:', 'report::', 'app-config::']);

if (! isset($options['db'])) {
    fwrite(STDERR, "Usage: php bin/import-legacy.php --db /path/to/vpnbot.sqlite [--from /path/to/config] [--app-config /path/to/app/config.php] [--report /path/to/report.log]\n");
    exit(1);
}

$root = dirname(__DIR__);
$configDirectory = resolvePath($options['from'] ?? ($root . '/config'));
$databasePath = resolvePath($options['db']);
$appConfigPath = resolvePath($options['app-config'] ?? ($root . '/app/config.php'));
$reportPath = isset($options['report']) ? resolvePath($options['report']) : null;

$factory = new ConnectionFactory();
$connection = $factory->create($databasePath);
$migrator = new Migrator($connection, $root . '/database/migrations');
$importer = new LegacyImporter($connection, $migrator, new FeatureRegistry());
$report = $importer->import($configDirectory, $appConfigPath);

$lines = ["Legacy import report"];

foreach ($report as $name => $count) {
    $lines[] = sprintf('%s: %d', $name, $count);
}

$output = implode(PHP_EOL, $lines) . PHP_EOL;
fwrite(STDOUT, $output);

if ($reportPath !== null) {
    $directory = dirname($reportPath);

    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException(sprintf('Failed to create report directory: %s', $directory));
    }

    file_put_contents($reportPath, $output);
}

function resolvePath(string $path): string
{
    if ($path === '') {
        return $path;
    }

    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) === 1) {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}
