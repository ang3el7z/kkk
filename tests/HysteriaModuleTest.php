<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/Hysteria/HysteriaConfigStore.php';
    require dirname(__DIR__) . '/src/Module/Hysteria/HysteriaModule.php';
    require dirname(__DIR__) . '/src/Module/Hysteria/HysteriaRuntime.php';
}

use VpnBot\Module\Hysteria\HysteriaConfigStore;
use VpnBot\Module\Hysteria\HysteriaModule;
use VpnBot\Module\Hysteria\HysteriaRuntime;

$configPath = dirname(__DIR__) . '/tmp/hysteria.test.yaml';
copy(dirname(__DIR__) . '/config/hysteria.yaml', $configPath);

$runtime = new class () implements HysteriaRuntime {
    public int $startCalls = 0;
    public int $stopCalls = 0;

    public function start(): string
    {
        $this->startCalls++;

        return 'start';
    }

    public function stop(): string
    {
        $this->stopCalls++;

        return 'stop';
    }
};

$module = new HysteriaModule(new HysteriaConfigStore($configPath), $runtime);

$config = $module->loadConfig();
assertHysteria(isset($config['listen']), 'module must load hysteria yaml');

$updated = $module->syncPassword($config, 'secret');
assertHysteria($module->extractPassword($updated) === 'secret', 'syncPassword must update auth password');

$module->saveAndRestart($updated, true);
assertHysteria($runtime->stopCalls === 1, 'saveAndRestart must stop runtime');
assertHysteria($runtime->startCalls === 1, 'saveAndRestart must start runtime when enabled');
assertHysteria($module->extractPassword($module->loadConfig()) === 'secret', 'saveAndRestart must persist yaml');

$module->saveAndRestart($config, false);
assertHysteria($runtime->stopCalls === 2, 'saveAndRestart must stop runtime on disable path');
assertHysteria($runtime->startCalls === 1, 'saveAndRestart must not start runtime when disabled');

@unlink($configPath);

echo "HysteriaModuleTest: OK\n";

function assertHysteria(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
