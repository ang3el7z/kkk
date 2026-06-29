<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/NaiveProxy/CaddyfileStore.php';
    require dirname(__DIR__) . '/src/Module/NaiveProxy/NaiveProxyModule.php';
    require dirname(__DIR__) . '/src/Module/NaiveProxy/NaiveProxyRuntime.php';
}

use VpnBot\Module\NaiveProxy\CaddyfileStore;
use VpnBot\Module\NaiveProxy\NaiveProxyModule;
use VpnBot\Module\NaiveProxy\NaiveProxyRuntime;

$configPath = dirname(__DIR__) . '/tmp/Caddyfile.test';
copy(dirname(__DIR__) . '/config/Caddyfile', $configPath);

$runtime = new class () implements NaiveProxyRuntime {
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

$module = new NaiveProxyModule(new CaddyfileStore($configPath), $runtime);

$config = $module->loadConfig();
assertNaive(str_contains($config, 'basic_auth _ __'), 'module must load Caddyfile text');

$updated = $module->updateBasicAuth($config, 'alice', 'secret');
assertNaive(str_contains($updated, 'basic_auth alice secret'), 'updateBasicAuth must replace credentials');

$credentials = $module->parseCredentials($updated);
assertNaive($credentials['user'] === 'alice', 'parseCredentials must parse user');
assertNaive($credentials['password'] === 'secret', 'parseCredentials must parse password');

$module->saveAndRestart($updated, true);
assertNaive($runtime->stopCalls === 1, 'saveAndRestart must stop runtime');
assertNaive($runtime->startCalls === 1, 'saveAndRestart must start runtime when enabled');
assertNaive(str_contains(file_get_contents($configPath) ?: '', 'basic_auth alice secret'), 'saveAndRestart must persist config');

$module->saveAndRestart($config, false);
assertNaive($runtime->stopCalls === 2, 'saveAndRestart must stop runtime on disable path');
assertNaive($runtime->startCalls === 1, 'saveAndRestart must not start runtime when disabled');

@unlink($configPath);

echo "NaiveProxyModuleTest: OK\n";

function assertNaive(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
