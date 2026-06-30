<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

final class BackupScheduleAction implements CronAction
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function tick(): void
    {
        $config = $this->bot->getPacConf();

        if (empty($config['backup'])) {
            return;
        }

        $now = time();
        [$start, $period] = explode('/', $config['backup']);
        $start = strtotime(trim($start));
        $period = strtotime(trim($period), 0);

        if (empty($start) || empty($period) || $now < $start) {
            return;
        }

        $lastScheduled = $start + (int) floor(($now - $start) / $period) * $period;
        $lastBackupTime = $config['last_backup_time'] ?? 0;

        if ($lastBackupTime >= $lastScheduled) {
            return;
        }

        $config['last_backup_time'] = $now;
        $this->bot->setPacConf($config);
        $this->bot->pinBackup();
    }
}
