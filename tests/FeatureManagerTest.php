<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Application/Feature/ContainerRuntime.php';
    require dirname(__DIR__) . '/src/Application/Feature/FeatureManager.php';
    require dirname(__DIR__) . '/src/Application/Feature/NoopContainerRuntime.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureDefinition.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRegistry.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Compose/ComposeOverrideWriter.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/SqliteFeatureRepository.php';
}

use VpnBot\Application\Feature\ContainerRuntime;
use VpnBot\Application\Feature\FeatureManager;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Compose\ComposeOverrideWriter;
use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteFeatureRepository;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-feature-manager-test.sqlite';
$overridePath = dirname(__DIR__) . '/tmp/vpnbot-feature-manager.override.yml';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

if (file_exists($overridePath)) {
    @unlink($overridePath);
}

$registry = new FeatureRegistry();
$connection = (new ConnectionFactory())->create($databasePath);
$migrator = new Migrator($connection, dirname(__DIR__) . '/database/migrations');
$migrator->migrate();

$repository = new SqliteFeatureRepository($connection, $registry);
$runtime = new class () implements ContainerRuntime {
    /**
     * @var list<list<string>>
     */
    public array $startCalls = [];

    /**
     * @var list<list<string>>
     */
    public array $stopCalls = [];

    public function start(array $services): void
    {
        $this->startCalls[] = $services;
    }

    public function stopAndRemove(array $services): void
    {
        $this->stopCalls[] = $services;
    }
};
$manager = new FeatureManager(
    $repository,
    $registry,
    new ComposeOverrideWriter($registry),
    $runtime,
    $overridePath,
);

$manager->disable('xray');

assertFeatureManager($repository->isEnabled('xray') === false, 'disable must persist xray=false in SQLite');
$override = file_get_contents($overridePath);
assertFeatureManager($override !== false, 'disable must write compose override file');
assertFeatureManager(str_contains($override, "xr:\n    profiles:\n      - \"disabled-xr\"\n"), 'disable must profile xray service out');
assertFeatureManager($runtime->stopCalls === [['xr']], 'disable must stop/remove xray service');
assertFeatureManager($runtime->startCalls === [], 'disable must not start services');

$manager->enable('xray');

assertFeatureManager($repository->isEnabled('xray') === true, 'enable must persist xray=true in SQLite');
$override = file_get_contents($overridePath);
assertFeatureManager($override !== false, 'enable must keep compose override file readable');
assertFeatureManager(! str_contains($override, "xr:\n    profiles:\n      - \"disabled-xr\"\n"), 'enable must remove disabled xray profile override');
assertFeatureManager($runtime->startCalls === [['xr']], 'enable must record xray start');
assertFeatureManager($manager->list()['xray'] === true, 'list must expose current feature state');

$connection = null;

@unlink($databasePath);
@unlink($overridePath);

echo "FeatureManagerTest: OK\n";

function assertFeatureManager(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
