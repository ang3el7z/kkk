<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureDefinition.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRegistry.php';
    require dirname(__DIR__) . '/src/Telegram/Menu/ContainerManagerMenuBuilder.php';
}

use VpnBot\Domain\Feature\FeatureDefinition;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Telegram\Menu\ContainerManagerMenuBuilder;

$builder = new ContainerManagerMenuBuilder();
$menu = $builder->build(
    new FeatureRegistry(),
    [
        'php' => true,
        'service' => true,
        'ng' => true,
        'up' => true,
        'xray' => false,
    ],
    static fn (FeatureDefinition $definition): string => strtoupper($definition->id()),
    'BACK',
);

$callbacks = [];

foreach ($menu['data'] as $row) {
    foreach ($row as $button) {
        $callbacks[] = $button['callback_data'] ?? null;
    }
}

assertContainerMenu(str_contains($menu['text'], 'Settings -> Container manager'), 'menu title must be rendered');
assertContainerMenu(str_contains($menu['text'], '🔒 PHP'), 'core service must be shown as locked');
assertContainerMenu(str_contains($menu['text'], '🔴 XRAY'), 'disabled feature must be shown as disabled');
assertContainerMenu(in_array('/featureToggle xray', $callbacks, true), 'toggle callback must be rendered for xray');
assertContainerMenu(in_array('/menu containers', $callbacks, true), 'locked row callback must stay on container menu');
assertContainerMenu($menu['data'][array_key_last($menu['data'])][0]['callback_data'] === '/menu config', 'back button must point to config menu');

echo "ContainerManagerMenuBuilderTest: OK\n";

function assertContainerMenu(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
