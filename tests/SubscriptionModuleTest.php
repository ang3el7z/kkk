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
    require dirname(__DIR__) . '/src/Module/Pac/SubscriptionModule.php';
}

use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteDocumentSettingsRepository;
use VpnBot\Module\Pac\PacTemplateStore;
use VpnBot\Module\Pac\SubscriptionModule;

$configDir = dirname(__DIR__) . '/tmp/subscription-module';
$databasePath = dirname(__DIR__) . '/tmp/subscription-module.sqlite';

if (! is_dir($configDir)) {
    mkdir($configDir, 0777, true);
}

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$connection = (new ConnectionFactory())->create($databasePath);
(new Migrator($connection, dirname(__DIR__) . '/database/migrations'))->migrate();
$repository = new SqliteDocumentSettingsRepository($connection, 'legacy.pac');
$repository->replaceAll([
    'v2raytemplates' => [
        'custom' => ['name' => 'v2-custom'],
    ],
]);
file_put_contents($configDir . '/v2ray.json', json_encode(['name' => 'origin'], JSON_PRETTY_PRINT));

$module = new SubscriptionModule(new PacTemplateStore($repository, $configDir));
$xray = [
    'inbounds' => [[
        'settings' => [
            'clients' => [
                [
                    'id' => 'uuid-1',
                    'email' => 'alice',
                    'v2raytemplate' => base64_encode('custom'),
                ],
            ],
        ],
    ]],
];

$match = $module->findClientByUuid($xray, 'uuid-1');
assertSubscription($match['index'] === 0, 'module must find client index by uuid');
assertSubscription($match['client']['email'] === 'alice', 'module must return matched client payload');

$resolved = $module->resolveTemplateForClient('s', $match['client']);
assertSubscription($resolved['name'] === 'v2-custom', 'module must resolve explicit user template from store');

$updated = $module->updateClientTemplate($xray, 'v2ray', 0, null);
assertSubscription(! isset($updated['inbounds'][0]['settings']['clients'][0]['v2raytemplate']), 'module must unset client template when null requested');

$updated = $module->updateClientTemplate($xray, 'v2ray', 0, base64_encode('origin'));
assertSubscription($updated['inbounds'][0]['settings']['clients'][0]['v2raytemplate'] === base64_encode('origin'), 'module must persist encoded template token');

@unlink($configDir . '/v2ray.json');
@unlink($databasePath);
@rmdir($configDir);

echo "SubscriptionModuleTest: OK\n";

function assertSubscription(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
