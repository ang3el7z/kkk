<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

use Throwable;

final class XrayStatsPollAction implements CronAction
{
    private int $lastRunAt = 0;

    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function tick(): void
    {
        if ($this->lastRunAt !== 0 && (time() - $this->lastRunAt) <= 60) {
            return;
        }

        $this->lastRunAt = time();

        try {
            $xray = $this->bot->getXray();
            $download = json_decode($this->bot->ssh('xray api stats --server=127.0.0.1:8080 -name "inbound>>>vless_tls>>>traffic>>>downlink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
            $upload = json_decode($this->bot->ssh('xray api stats --server=127.0.0.1:8080 -name "inbound>>>vless_tls>>>traffic>>>uplink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
            $stats = $this->bot->getXrayStats();
            $stats['session'] = [
                'download' => $download,
                'upload' => $upload,
            ];

            $users = $xray['inbounds'][0]['settings']['clients'] ?? [];

            if ($users !== []) {
                $tmp = [];

                foreach ($users as $index => $user) {
                    $d = json_decode($this->bot->ssh('xray api stats --server=127.0.0.1:8080 -name "user>>>' . $user['email'] . '>>>traffic>>>downlink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
                    $u = json_decode($this->bot->ssh('xray api stats --server=127.0.0.1:8080 -name "user>>>' . $user['email'] . '>>>traffic>>>uplink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
                    $tmp[$index] = [
                        'session' => [
                            'download' => $d,
                            'upload' => $u,
                        ],
                        'global' => [
                            'download' => $stats['users'][$index]['global']['download'],
                            'upload' => $stats['users'][$index]['global']['upload'],
                        ],
                    ];
                }

                $stats['users'] = $tmp;
            }

            $this->bot->setXrayStats($stats);
        } catch (Throwable) {
        }
    }
}
