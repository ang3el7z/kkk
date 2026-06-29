<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureDefinition.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRegistry.php';
}

use VpnBot\Domain\Feature\FeatureRegistry;

$registry = new FeatureRegistry();

assertFeature($registry->get('php')->toggleable() === false, 'core service php must not be toggleable');
assertFeature($registry->get('service')->toggleable() === false, 'core service service must not be toggleable');
assertFeature($registry->get('ng')->toggleable() === false, 'core service ng must not be toggleable');
assertFeature($registry->get('up')->toggleable() === false, 'core service up must not be toggleable');

$serviceMap = [
    'php' => 'php',
    'service' => 'service',
    'ng' => 'ng',
    'up' => 'up',
    'wg' => 'wireguard',
    'wg1' => 'wireguard_1',
    'xr' => 'xray',
    'oc' => 'openconnect',
    'np' => 'naive',
    'wp' => 'warp',
    'proxy' => 'proxy',
    'ss' => 'shadowsocks',
    'dnstt' => 'dnstt',
    'hy' => 'hysteria',
    'ad' => 'adguard',
    'tg' => 'mtproto',
];

foreach ($serviceMap as $service => $expectedFeatureId) {
    $definition = $registry->findByService($service);

    assertFeature($definition !== null, sprintf('service "%s" must resolve to a feature', $service));
    assertFeature(
        $definition->id() === $expectedFeatureId,
        sprintf('service "%s" resolved to "%s", expected "%s"', $service, $definition->id(), $expectedFeatureId)
    );
}

foreach ($registry->all() as $definition) {
    assertFeature(
        $definition->enabledByDefault() === true,
        sprintf('feature "%s" must be enabled by default', $definition->id())
    );
}

assertFeature($registry->findByMenuKey('/xray 2')?->id() === 'xray', 'xray menu lookup must resolve');
assertFeature($registry->findByMenuKey('/menu adguard')?->id() === 'adguard', 'adguard menu lookup must resolve');
assertFeature($registry->findByMenuKey('/changePort hy')?->id() === 'hysteria', 'hysteria menu lookup must resolve');
assertFeature($registry->findByMenuKey('/menu wg 1')?->id() === 'wireguard_1', 'wireguard_1 menu lookup must resolve');

echo "FeatureRegistryTest: OK\n";

function assertFeature(bool $condition, string $message): void
{
    if (! $condition) {
        throw new \RuntimeException($message);
    }
}
