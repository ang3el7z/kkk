<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/Shadowsocks/ShadowsocksConfigStore.php';
    require dirname(__DIR__) . '/src/Module/Shadowsocks/ShadowsocksModule.php';
    require dirname(__DIR__) . '/src/Module/Shadowsocks/ShadowsocksRuntime.php';
}

use VpnBot\Module\Shadowsocks\ShadowsocksConfigStore;
use VpnBot\Module\Shadowsocks\ShadowsocksModule;
use VpnBot\Module\Shadowsocks\ShadowsocksRuntime;

$dir = dirname(__DIR__) . '/tmp/shadowsocks-module';
$serverPath = $dir . '/ssserver.json';
$localPath = $dir . '/sslocal.json';

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

copy(dirname(__DIR__) . '/config/ssserver.json', $serverPath);
copy(dirname(__DIR__) . '/config/sslocal.json', $localPath);

$runtime = new class () implements ShadowsocksRuntime {
    /** @var list<string> */
    public array $calls = [];

    public function startServer(): string
    {
        $this->calls[] = 'startServer';

        return 'startServer';
    }

    public function stopServer(): string
    {
        $this->calls[] = 'stopServer';

        return 'stopServer';
    }

    public function startLocal(): string
    {
        $this->calls[] = 'startLocal';

        return 'startLocal';
    }

    public function stopLocal(): string
    {
        $this->calls[] = 'stopLocal';

        return 'stopLocal';
    }
};

$module = new ShadowsocksModule(new ShadowsocksConfigStore($serverPath, $localPath), $runtime);

$server = $module->loadServerConfig();
$local = $module->loadLocalConfig();
assertShadowsocks(isset($server['method']), 'module must load server config');
assertShadowsocks(isset($local['method']), 'module must load local config');

[$server, $local] = $module->syncPassword($server, $local, 'secret');
assertShadowsocks($server['password'] === 'secret', 'syncPassword must set server password');
assertShadowsocks($local['password'] === 'secret', 'syncPassword must set local password');

[$server, $local] = $module->toggleV2rayPlugin($server, $local, 'example.org', true, 8388);
assertShadowsocks($server['plugin'] === 'v2ray-plugin', 'toggleV2rayPlugin must enable server plugin');
assertShadowsocks($local['server'] === 'up', 'toggleV2rayPlugin must point local client to upstream service');

$details = $module->buildConnectionDetails($server, 'example.org', 8388, 'hash', true);
assertShadowsocks($details['plugin_enabled'] === true, 'buildConnectionDetails must expose plugin state');
assertShadowsocks(str_contains($details['link'], 'example.org:443'), 'buildConnectionDetails must use TLS port when plugin enabled');
assertShadowsocks(str_contains($details['options'], 'path=/v2rayhash;host=example.org'), 'buildConnectionDetails must include v2ray path and host');

$module->saveAndRestart($server, $local);
assertShadowsocks($runtime->calls === ['stopLocal', 'stopServer', 'startServer', 'startLocal'], 'saveAndRestart must restart in stable order');

$savedServer = json_decode(file_get_contents($serverPath) ?: '', true);
assertShadowsocks(($savedServer['password'] ?? null) === 'secret', 'saveAndRestart must persist server config');

$runtime->calls = [];
$server['server_port'] = 9999;
$module->saveServerAndRestart($server);
assertShadowsocks($runtime->calls === ['stopServer', 'startServer'], 'saveServerAndRestart must only bounce server');

$runtime->calls = [];
$local['server_port'] = 1081;
$module->saveLocalAndRestart($local);
assertShadowsocks($runtime->calls === ['stopLocal', 'startLocal'], 'saveLocalAndRestart must only bounce local proxy');

@unlink($serverPath);
@unlink($localPath);
@rmdir($dir);

echo "ShadowsocksModuleTest: OK\n";

function assertShadowsocks(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
