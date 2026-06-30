<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/Mtproto/MtprotoConfigStore.php';
    require dirname(__DIR__) . '/src/Module/Mtproto/MtprotoModule.php';
    require dirname(__DIR__) . '/src/Module/Mtproto/MtprotoRuntime.php';
}

use VpnBot\Module\Mtproto\MtprotoConfigStore;
use VpnBot\Module\Mtproto\MtprotoModule;
use VpnBot\Module\Mtproto\MtprotoRuntime;

$dir = dirname(__DIR__) . '/tmp/mtproto-module';

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$runtime = new class () implements MtprotoRuntime {
    /** @var list<string> */
    public array $calls = [];
    public bool $running = false;

    public function stop(): string
    {
        $this->calls[] = 'stop';
        $this->running = false;

        return 'stop';
    }

    public function start(string $command): string
    {
        $this->calls[] = $command;
        $this->running = true;

        return 'start';
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
};

$store = new MtprotoConfigStore(
    $dir . '/mtprotosecret',
    $dir . '/mtprotodomain',
    $dir . '/mtprotoadtag'
);
$module = new MtprotoModule($store, $runtime);

$module->saveConfig([
    'secret' => '1234567890abcdef1234567890abcdef',
    'domain' => 'yandex.ru',
    'adtag' => '',
]);
$config = $module->loadConfig();
assertMtproto($config['domain'] === 'yandex.ru', 'store must load persisted domain');

assertMtproto($module->normalizeAdtag('0') === '', 'normalizeAdtag must disable adtag on 0');
assertMtproto($module->normalizeAdtag('ABCDEF1234567890ABCDEF1234567890') === 'abcdef1234567890abcdef1234567890', 'normalizeAdtag must lowercase valid adtag');
assertMtproto($module->normalizeAdtag('bad') === null, 'normalizeAdtag must reject invalid adtag');

$link = $module->buildLink($config, 'example.org', 443);
assertMtproto(str_contains($link, 'server=example.org'), 'buildLink must include server');
assertMtproto(str_contains($link, 'secret=ee1234567890abcdef1234567890abcdef'), 'buildLink must include secret prefix');

$runtime->calls = [];
$module->restart($config, '1.2.3.4');
assertMtproto($runtime->calls[0] === 'stop', 'restart must stop runtime first');
assertMtproto(str_contains($runtime->calls[1], '--domain yandex.ru'), 'restart must start proxy with fake domain');
assertMtproto(str_contains($runtime->calls[1], '--nat-info 10.10.0.8:1.2.3.4'), 'restart must inject public ip');

$state = $module->buildMenuState($config, true, 'example.org', 443);
assertMtproto($state['status'] === 'on', 'buildMenuState must render running state');
assertMtproto($state['adtag'] === 'off', 'buildMenuState must render disabled adtag');
assertMtproto($state['link'] !== null, 'buildMenuState must include link when running');

$runtime->calls = [];
$module->restart(['secret' => 'bad', 'domain' => '', 'adtag' => ''], '1.2.3.4');
assertMtproto($runtime->calls === ['stop'], 'restart must not start proxy when secret invalid');

@unlink($dir . '/mtprotosecret');
@unlink($dir . '/mtprotodomain');
@unlink($dir . '/mtprotoadtag');
@rmdir($dir);

echo "MtprotoModuleTest: OK\n";

function assertMtproto(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
