<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Settings/SettingsRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Storage/LegacyPacSettingsRepository.php';
}

use VpnBot\Infrastructure\Storage\LegacyPacSettingsRepository;

$path = dirname(__DIR__) . '/tmp/pac-settings-test.json';

if (file_exists($path)) {
    @unlink($path);
}

$repository = new LegacyPacSettingsRepository($path);
$repository->replaceAll([
    'language' => 'en',
    'limitpage' => 5,
]);
$repository->set('domain', 'example.com');

$settings = json_decode((string) file_get_contents($path), true);

assertLegacyPac($settings === [
    'language' => 'en',
    'limitpage' => 5,
    'domain' => 'example.com',
], 'legacy pac adapter must preserve pac.json object format');

@unlink($path);

echo "LegacyPacSettingsRepositoryTest: OK\n";

function assertLegacyPac(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
