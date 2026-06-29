<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/OpenConnect/OpenConnectConfigStore.php';
    require dirname(__DIR__) . '/src/Module/OpenConnect/OpenConnectModule.php';
    require dirname(__DIR__) . '/src/Module/OpenConnect/OpenConnectRuntime.php';
}

use VpnBot\Module\OpenConnect\OpenConnectConfigStore;
use VpnBot\Module\OpenConnect\OpenConnectModule;
use VpnBot\Module\OpenConnect\OpenConnectRuntime;

$configPath = dirname(__DIR__) . '/tmp/ocserv.test.conf';
$passwdPath = dirname(__DIR__) . '/tmp/ocserv.test.passwd';
copy(dirname(__DIR__) . '/config/ocserv.conf', $configPath);
file_put_contents($passwdPath, "alice:hash\nbob:hash\n");

$runtime = new class () implements OpenConnectRuntime {
    public int $startCalls = 0;
    public int $stopCalls = 0;
    /** @var list<array{user:string,password:string}> */
    public array $setPasswords = [];
    /** @var list<string> */
    public array $deletedUsers = [];

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

    public function setUserPassword(string $username, string $password): string
    {
        $this->setPasswords[] = ['user' => $username, 'password' => $password];
        return 'set';
    }

    public function deleteUser(string $username): string
    {
        $this->deletedUsers[] = $username;
        return 'delete';
    }
};

$module = new OpenConnectModule(new OpenConnectConfigStore($configPath, $passwdPath), $runtime);

$config = $module->loadConfig();
assertOpenConnect(str_contains($config, 'dns = 10.10.0.5'), 'module must load ocserv config text');
assertOpenConnect($module->loadUsers() !== [], 'module must load users from passwd file');

$updated = $module->updateDns($config, '1.1.1.1');
assertOpenConnect(str_contains($updated, 'dns = 1.1.1.1'), 'updateDns must replace dns line');

$updated = $module->updateCamouflageSecret($updated, 'secret');
assertOpenConnect(str_contains($updated, 'camouflage_secret = "secret"'), 'updateCamouflageSecret must replace secret line');

$updated = $module->updateDefaultDomain($updated, 'oc.example.org');
assertOpenConnect(str_contains($updated, 'default-domain = oc.example.org'), 'updateDefaultDomain must replace default domain');

$toggled = $module->toggleExposeIRoutes($updated);
assertOpenConnect(str_contains($toggled, 'expose-iroutes = true'), 'toggleExposeIRoutes must flip expose-iroutes flag');

$routed = $module->applyRoutes($toggled, ['10.0.0.0/24', 'default']);
assertOpenConnect(str_contains($routed, "route = 10.0.0.0/24"), 'applyRoutes must inject subnet route');
assertOpenConnect(str_contains($routed, "route = default"), 'applyRoutes must preserve default route');

$state = $module->parseMenuState($routed);
assertOpenConnect($state['camouflage_secret'] === 'secret', 'parseMenuState must parse camouflage secret');
assertOpenConnect($state['dns'] === '1.1.1.1', 'parseMenuState must parse dns');
assertOpenConnect($state['expose_iroutes'] === true, 'parseMenuState must parse expose-iroutes state');

$module->syncAllUserPasswords(['alice', 'bob'], 'pass');
assertOpenConnect(count($runtime->setPasswords) === 2, 'syncAllUserPasswords must update every user');

$module->addUser('charlie', 'pass');
$module->deleteUser('alice');
assertOpenConnect($runtime->setPasswords[2]['user'] === 'charlie', 'addUser must delegate to runtime');
assertOpenConnect($runtime->deletedUsers === ['alice'], 'deleteUser must delegate to runtime');

$module->saveAndRestart($routed, true);
assertOpenConnect($runtime->stopCalls === 1, 'saveAndRestart must stop runtime');
assertOpenConnect($runtime->startCalls === 1, 'saveAndRestart must start runtime when enabled');

@unlink($configPath);
@unlink($passwdPath);

echo "OpenConnectModuleTest: OK\n";

function assertOpenConnect(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
