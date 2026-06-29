<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Settings/SettingsRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Storage/LegacyPacSettingsRepository.php';
    require dirname(__DIR__) . '/src/Module/Pac/PacTemplateStore.php';
}

use VpnBot\Infrastructure\Storage\LegacyPacSettingsRepository;
use VpnBot\Module\Pac\PacTemplateStore;

$configDir = dirname(__DIR__) . '/tmp/pac-template-store';
$pacPath = $configDir . '/pac.json';

if (! is_dir($configDir)) {
    mkdir($configDir, 0777, true);
}

file_put_contents($pacPath, json_encode([
    'v2raytemplates' => [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($configDir . '/v2ray.json', json_encode(['tag' => 'origin'], JSON_PRETTY_PRINT));

$store = new PacTemplateStore(new LegacyPacSettingsRepository($pacPath), $configDir);

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
@unlink($pacPath);
@rmdir($configDir);

echo "PacTemplateStoreTest: OK\n";

function assertPacTemplate(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
