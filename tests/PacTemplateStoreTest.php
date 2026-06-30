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
    require dirname(__DIR__) . '/src/Module/Pac/PacTemplateStore.php';
}

use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteDocumentSettingsRepository;
use VpnBot\Module\Pac\PacTemplateStore;

$configDir = dirname(__DIR__) . '/tmp/pac-template-store';
$databasePath = dirname(__DIR__) . '/tmp/pac-template-store.sqlite';

if (! is_dir($configDir)) {
    mkdir($configDir, 0777, true);
}

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$connection = (new ConnectionFactory())->create($databasePath);
(new Migrator($connection, dirname(__DIR__) . '/database/migrations'))->migrate();
file_put_contents($configDir . '/v2ray.json', json_encode(['tag' => 'origin'], JSON_PRETTY_PRINT));

$store = new PacTemplateStore(new SqliteDocumentSettingsRepository($connection, 'legacy.pac'), $configDir);

assertPacTemplate($store->loadOrigin('v2ray')['tag'] === 'origin', 'store must read origin template from config directory');

$store->saveTemplate('v2ray', 'custom', ['tag' => 'custom']);
assertPacTemplate($store->allTemplates('v2ray')['custom']['tag'] === 'custom', 'store must persist named template');

$store->setDefaultTemplate('v2ray', base64_encode('custom'));
assertPacTemplate($store->defaultTemplateToken('v2ray') === base64_encode('custom'), 'store must persist default template token');
assertPacTemplate($store->resolveTemplateDocument('v2ray')['tag'] === 'custom', 'default template must resolve when explicit token absent');

$store->saveOrigin('v2ray', ['tag' => 'origin-updated']);
assertPacTemplate($store->resolveTemplateDocument('v2ray', base64_encode('origin'))['tag'] === 'origin-updated', 'origin token must resolve to current origin file');

$store->deleteTemplate('v2ray', 'custom');
assertPacTemplate(! isset($store->allTemplates('v2ray')['custom']), 'deleteTemplate must remove named template');

@unlink($configDir . '/v2ray.json');
@unlink($databasePath);
@rmdir($configDir);

echo "PacTemplateStoreTest: OK\n";

function assertPacTemplate(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
