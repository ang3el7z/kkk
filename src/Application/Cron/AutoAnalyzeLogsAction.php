<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

use Exception;

final class AutoAnalyzeLogsAction implements CronAction
{
    private int $lastRunAt = 0;

    public function __construct(
        private readonly object $bot,
        private readonly string $configPath = __DIR__ . '/../../../app/config.php',
    ) {
    }

    public function tick(): void
    {
        try {
            $pac = $this->bot->getPacConf();

            if (empty($pac['autoscan'])) {
                return;
            }

            require $this->configPath;

            if (empty($c['admin']) || ($this->lastRunAt !== 0 && (time() - $this->lastRunAt) <= $pac['autoscan_timeout'])) {
                return;
            }

            $this->lastRunAt = time();
            $result = $this->bot->analysisIp(return: 1);

            if (empty($result)) {
                return;
            }

            $text = '';
            $buttons = [];
            $banned = 0;

            foreach ($result as $ip => $reasons) {
                foreach ($reasons as $reason) {
                    $reasonCounts[$reason['title']][$ip] = 1;
                }
            }

            foreach ($reasonCounts ?? [] as $title => $reasonCount) {
                $text .= "\n" . count($reasonCount) . " $title";
            }

            if (! empty($pac['autodeny'])) {
                $this->bot->denyIp(array_keys($result));
                $banned = count(array_keys($result));

                foreach (array_keys($result) as $ip) {
                    $buttons[] = [[
                        'text' => $ip,
                        'callback_data' => "/searchLogs $ip",
                    ]];
                }
            }

            if ($pac['silence'] != 0 && $pac['silence'] != 1) {
                return;
            }

            foreach ($c['admin'] as $admin) {
                $this->bot->send(
                    $admin,
                    "suspicious ips found: $text" . ($banned ? "\nbanned:$banned" : ''),
                    button: $buttons ?: [[[
                        'text' => $this->bot->i18n('analyze'),
                        'callback_data' => '/analysisIp',
                    ]]],
                    disable_notification: $pac['silence'] ? true : false
                );
            }
        } catch (Exception $e) {
            file_put_contents('/logs/php_error', $e->getMessage());
        }
    }
}
