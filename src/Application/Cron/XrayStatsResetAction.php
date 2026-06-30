<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

final class XrayStatsResetAction implements CronAction
{
    public function __construct(
        private readonly object $bot,
        private readonly string $configPath = __DIR__ . '/../../../app/config.php',
    ) {
    }

    public function tick(): void
    {
        $config = $this->bot->getPacConf();

        if (empty($config['reset_monthly'])) {
            return;
        }

        $now = time();
        $start = strtotime('first day of previous month midnight');
        $period = strtotime('1 month', 0);

        if (empty($start) || empty($period) || $now < $start) {
            return;
        }

        $lastScheduled = $start + (int) floor(($now - $start) / $period) * $period;
        $lastResetTime = $config['last_reset_xray_time'] ?? 0;

        if ($lastResetTime >= $lastScheduled) {
            return;
        }

        $config['last_reset_xray_time'] = $now;
        $this->bot->setPacConf($config);
        $this->bot->resetXrStats(1);

        require $this->configPath;

        foreach ($c['admin'] as $admin) {
            $this->bot->send($admin, 'vless: reset stats');
        }
    }
}
