<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/AdGuard/AdGuardConfigRepository.php';
    require dirname(__DIR__) . '/src/Module/AdGuard/AdGuardModule.php';
    require dirname(__DIR__) . '/src/Module/AdGuard/AdGuardRuntime.php';
}

use VpnBot\Module\AdGuard\AdGuardConfigRepository;
use VpnBot\Module\AdGuard\AdGuardModule;
use VpnBot\Module\AdGuard\AdGuardRuntime;

$store = new class () implements AdGuardConfigRepository {
    /**
     * @var array<string, mixed>
     */
    public array $config = [
        'users' => [
            ['name' => 'admin', 'password' => ''],
        ],
        'dns' => [
            'upstream_dns' => ['1.1.1.1'],
        ],
        'tls' => [],
        'filtering' => [
            'safe_search' => ['enabled' => false],
        ],
    ];

    public function load(): array
    {
        return $this->config;
    }

    public function save(array $config): void
    {
        $this->config = $config;
    }
};

$runtime = new class () implements AdGuardRuntime {
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

$module = new AdGuardModule($store, $runtime);

$config = $module->loadConfig();
assertAdGuard(isset($config['dns']['upstream_dns'][0]), 'module must load AdGuard yaml config');

$config = $module->syncPasswordAndTls($config, 'secret', true, 'example.org');
assertAdGuard(password_verify('secret', $config['users'][0]['password']), 'syncPasswordAndTls must hash password');
assertAdGuard($config['tls']['enabled'] === true, 'syncPasswordAndTls must enable TLS when requested');
assertAdGuard($config['tls']['server_name'] === 'example.org', 'syncPasswordAndTls must persist TLS server name');

$config = $module->syncXrayClients($config, [
    ['id' => 'uuid-1', 'email' => 'alice'],
    ['id' => 'uuid-2', 'email' => 'bob'],
]);
assertAdGuard(count($config['clients']['persistent']) === 2, 'syncXrayClients must render persistent client entries');
assertAdGuard($config['clients']['persistent'][0]['ids'][0] === 'uuid-1', 'syncXrayClients must carry client ids');

$config = $module->setAllowedClients($config, ['10.10.0.0/24', 'uuid-1', 'uuid-1'], false);
assertAdGuard($config['dns']['allowed_clients'] === ['10.10.0.0/24', 'uuid-1'], 'setAllowedClients must deduplicate allowed clients');

$config = $module->addUpstream($config, '9.9.9.9');
assertAdGuard(in_array('9.9.9.9', $config['dns']['upstream_dns'], true), 'addUpstream must append dns upstream');

$config = $module->removeUpstream($config, array_search('9.9.9.9', $config['dns']['upstream_dns'], true));
assertAdGuard(! in_array('9.9.9.9', $config['dns']['upstream_dns'], true), 'removeUpstream must delete selected upstream');

$module->saveConfig($config, true);
assertAdGuard($runtime->stopCalls === 1, 'saveConfig(restart=true) must stop runtime');
assertAdGuard($runtime->startCalls === 1, 'saveConfig(restart=true) must start runtime');
assertAdGuard($store->config['users'][0]['password'] === $config['users'][0]['password'], 'saveConfig must persist updated config into repository');

echo "AdGuardModuleTest: OK\n";

function assertAdGuard(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
