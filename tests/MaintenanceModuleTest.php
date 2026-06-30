<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/Maintenance/LogStore.php';
    require dirname(__DIR__) . '/src/Module/Maintenance/MaintenanceModule.php';
    require dirname(__DIR__) . '/src/Module/Maintenance/MaintenanceRuntime.php';
    require dirname(__DIR__) . '/src/Module/Maintenance/UpdateStateStore.php';
}

use VpnBot\Module\Maintenance\LogStore;
use VpnBot\Module\Maintenance\MaintenanceModule;
use VpnBot\Module\Maintenance\MaintenanceRuntime;
use VpnBot\Module\Maintenance\UpdateStateStore;

$dir = dirname(__DIR__) . '/tmp/maintenance-module';
$logsDir = $dir . '/logs';
$updateDir = $dir . '/update';

@mkdir($logsDir, 0777, true);
@mkdir($updateDir, 0777, true);
file_put_contents($logsDir . '/a.log', 'abc');
file_put_contents($logsDir . '/b.log', 'xyz');
file_put_contents($updateDir . '/branch', "master\n");

$runtime = new class () implements MaintenanceRuntime {
    public function currentBranch(): string
    {
        return 'master';
    }

    public function branchStatusLines(): array
    {
        return ['* master 123 [origin/master] msg'];
    }
};

$module = new MaintenanceModule(new LogStore($logsDir), new UpdateStateStore($updateDir), $runtime);

$logs = $module->logs();
assertMaintenance(count($logs) === 2, 'logs must list files');
assertMaintenance($module->logByIndex(0) !== null, 'logByIndex must resolve entry');

$module->clearLogByIndex(0);
assertMaintenance(file_get_contents($logs[0]['path']) === '', 'clearLogByIndex must truncate log');

$module->deleteLogByIndex(1);
assertMaintenance(count($module->logs()) === 1, 'deleteLogByIndex must remove selected log');

$module->clearAllLogs();
assertMaintenance(file_get_contents($logs[0]['path']) === '', 'clearAllLogs must truncate remaining logs');

$desc = $module->describeSchedule('2026-01-01 10:00 / 12 hours');
assertMaintenance($desc['valid'] === true && str_contains((string) $desc['display'], 'start / 12 hours period'), 'describeSchedule must format valid schedule');
assertMaintenance($module->describeSchedule('bad')['valid'] === false, 'describeSchedule must flag invalid schedule');
assertMaintenance($module->normalizeSchedule('2026-01-01 10:00 / 12 hours') === '2026-01-01 10:00 / 12 hours', 'normalizeSchedule must normalize valid input');
assertMaintenance($module->normalizeSchedule('bad') === null, 'normalizeSchedule must reject invalid input');

$module->storeReloadRequest('1:2', 'key', ['chat_id' => 1], '2');
assertMaintenance(trim(file_get_contents($updateDir . '/reload_message')) === '1:2', 'storeReloadRequest must persist reload message');
assertMaintenance($module->trackedBranch() === 'master', 'trackedBranch must read update branch');
assertMaintenance($module->currentBranch() === 'master', 'currentBranch must use runtime');
assertMaintenance(count($module->branchStatusLines()) === 1, 'branchStatusLines must use runtime');

$module->clearReloadState();
assertMaintenance(file_get_contents($updateDir . '/reload_message') === '', 'clearReloadState must clear reload marker');

@unlink($logsDir . '/a.log');
@unlink($logsDir . '/b.log');
@unlink($updateDir . '/branch');
@unlink($updateDir . '/reload_message');
@unlink($updateDir . '/key');
@unlink($updateDir . '/curl');
@unlink($updateDir . '/pipe');
@unlink($updateDir . '/message');
@rmdir($logsDir);
@rmdir($updateDir);
@rmdir($dir);

echo "MaintenanceModuleTest: OK\n";

function assertMaintenance(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
