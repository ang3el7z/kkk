<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Settings/SettingsRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Storage/LegacyPacSettingsRepository.php';
    require dirname(__DIR__) . '/src/Module/Pac/PacTemplateStore.php';
    require dirname(__DIR__) . '/src/Module/Pac/SubscriptionModule.php';
}

use VpnBot\Infrastructure\Storage\LegacyPacSettingsRepository;
use VpnBot\Module\Pac\PacTemplateStore;
use VpnBot\Module\Pac\SubscriptionModule;

$configDir = dirname(__DIR__) . '/tmp/subscription-module';
$pacPath = $configDir . '/pac.json';

if (! is_dir($configDir)) {
    mkdir($configDir, 0777, true);
}

file_put_contents($pacPath, json_encode([
    'v2raytemplates' => [
        'custom' => ['name' => 'v2-custom'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($configDir . '/v2ray.json', json_encode(['name' => 'origin'], JSON_PRETTY_PRINT));

$module = new SubscriptionModule(new PacTemplateStore(new LegacyPacSettingsRepository($pacPath), $configDir));
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
@unlink($pacPath);
@rmdir($configDir);

echo "SubscriptionModuleTest: OK\n";

function assertSubscription(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
