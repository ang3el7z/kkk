<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

final class LogCleanupScheduleAction implements CronAction
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function tick(): void
    {
        $config = $this->bot->getPacConf();

        if (empty($config['autocleanlogs'])) {
            return;
        }

        $now = time();
        [$start, $period] = explode('/', $config['autocleanlogs']);
        $start = strtotime(trim($start));
        $period = strtotime(trim($period), 0);

        if (empty($start) || empty($period) || $now < $start) {
            return;
        }

        $lastScheduled = $start + (int) floor(($now - $start) / $period) * $period;
        $lastCleanTime = $config['last_clean_logs_time'] ?? 0;

        if ($lastCleanTime >= $lastScheduled) {
            return;
        }

        $config['last_clean_logs_time'] = $now;
        $this->bot->setPacConf($config);
        $this->bot->cleanLog();
    }
}
