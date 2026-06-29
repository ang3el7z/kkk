<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Settings/SettingsRepository.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/ConnectionFactory.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/Migrator.php';
    require dirname(__DIR__) . '/src/Infrastructure/Database/SqliteSettingsRepository.php';
    require dirname(__DIR__) . '/src/Module/Xray/SqliteXrayStateRepository.php';
    require dirname(__DIR__) . '/src/Module/Xray/XrayConfigCodec.php';
    require dirname(__DIR__) . '/src/Module/Xray/XrayModule.php';
    require dirname(__DIR__) . '/src/Module/Xray/XrayRuntime.php';
}

use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteSettingsRepository;
use VpnBot\Module\Xray\SqliteXrayStateRepository;
use VpnBot\Module\Xray\XrayConfigCodec;
use VpnBot\Module\Xray\XrayModule;
use VpnBot\Module\Xray\XrayRuntime;

$databasePath = dirname(__DIR__) . '/tmp/vpnbot-xray-module-test.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

$connection = (new ConnectionFactory())->create($databasePath);
(new Migrator($connection, dirname(__DIR__) . '/database/migrations'))->migrate();
$settings = new SqliteSettingsRepository($connection);
$repository = new SqliteXrayStateRepository($connection, $settings);
$codec = new XrayConfigCodec();

$template = $codec->parse(file_get_contents(dirname(__DIR__) . '/config/xray.json'));
$template['inbounds'][0]['settings']['clients'] = [];
$settings->set('legacy.xray_config', $template);
$repository->saveUsers([
    [
        'id' => 'uuid-1',
        'email' => 'alice',
        'flow' => 'xtls-rprx-vision',
        'time' => 1_735_689_600,
    ],
]);
$repository->saveStats([
    'global' => ['upload' => 5, 'download' => 10],
    'session' => ['upload' => 1, 'download' => 2],
    'users' => [
        0 => [
            'global' => ['upload' => 3, 'download' => 4],
            'session' => ['upload' => 1, 'download' => 1],
        ],
    ],
]);

$runtime = new class () implements XrayRuntime {
    /**
     * @var array<int, array{config: array<string, mixed>, restart: bool}>
     */
    public array $calls = [];

    public function apply(array $config, bool $restart): void
    {
        $this->calls[] = [
            'config' => $config,
            'restart' => $restart,
        ];
    }
};

$module = new XrayModule($codec, $repository, $runtime);

$config = $module->getConfig();
assertXray(isset($config['inbounds'][0]['settings']['clients'][0]['email']), 'module must expose client from DB state');
assertXray($config['inbounds'][0]['settings']['clients'][0]['email'] === 'alice', 'DB client must be rendered into config');

$config['inbounds'][0]['settings']['clients'][] = [
    'id' => 'uuid-2',
    'email' => 'bob',
];
$module->saveConfig($config, false);

assertXray(count($runtime->calls) === 1, 'saveConfig must call runtime exactly once');
assertXray($runtime->calls[0]['restart'] === false, 'saveConfig must pass restart flag through');
assertXray(isset($runtime->calls[0]['config']['api']['tag']), 'normalized config must include API section');
assertXray(isset($runtime->calls[0]['config']['policy']['levels']->{'0'}), 'normalized config must include stats policy');
assertXray(count($repository->loadUsers()) === 2, 'saveConfig must sync xray_users table');

$stats = $module->getStats();
assertXray($stats['global']['download'] === 10, 'module must read DB-backed global stats');
assertXray($stats['users'][0]['session']['upload'] === 1, 'module must read DB-backed user stats');

$parsedSample = $codec->parse(file_get_contents(dirname(__DIR__) . '/config/xray.json'));
assertXray(($parsedSample['inbounds'][0]['protocol'] ?? null) === 'vless', 'sample xray.json must be parseable');

$connection = null;
@unlink($databasePath);

echo "XrayModuleTest: OK\n";

function assertXray(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
