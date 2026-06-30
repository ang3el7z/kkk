<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

final class CronRunner
{
    /**
     * @param list<CronAction> $actions
     */
    public function __construct(
        private readonly array $actions,
        private readonly int $periodSeconds = 10,
    ) {
    }

    public function runTick(): void
    {
        foreach ($this->actions as $action) {
            $action->tick();
        }
    }

    public function runLoop(?int $iterations = null): void
    {
        $executed = 0;

        while ($iterations === null || $executed < $iterations) {
            $this->runTick();
            $executed++;

            if ($iterations !== null && $executed >= $iterations) {
                return;
            }

            sleep($this->periodSeconds);
        }
    }
}
