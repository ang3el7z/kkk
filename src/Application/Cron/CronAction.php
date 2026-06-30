<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

interface CronAction
{
    public function tick(): void;
}
