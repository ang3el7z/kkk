<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Application/Cron/CronAction.php';
    require dirname(__DIR__) . '/src/Application/Cron/CronRunner.php';
}

use VpnBot\Application\Cron\CronAction;
use VpnBot\Application\Cron\CronRunner;

$calls = [];

$runner = new CronRunner([
    new class ($calls) implements CronAction {
        public function __construct(
            private array &$calls,
        ) {
        }

        public function tick(): void
        {
            $this->calls[] = 'a';
        }
    },
    new class ($calls) implements CronAction {
        public function __construct(
            private array &$calls,
        ) {
        }

        public function tick(): void
        {
            $this->calls[] = 'b';
        }
    },
], 0);

$runner->runTick();
assertCron($calls === ['a', 'b'], 'runTick must execute all actions in order');

$calls = [];
$runner = new CronRunner([
    new class ($calls) implements CronAction {
        public function __construct(
            private array &$calls,
        ) {
        }

        public function tick(): void
        {
            $this->calls[] = 'tick';
        }
    },
], 0);

$runner->runLoop(1);
assertCron($calls === ['tick'], 'runLoop(1) must execute one dry-run tick without endless loop');

echo "CronRunnerTest: OK\n";

function assertCron(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
