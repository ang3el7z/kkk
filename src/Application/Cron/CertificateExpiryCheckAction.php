<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

use Exception;

final class CertificateExpiryCheckAction implements CronAction
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
            require $this->configPath;

            if (empty($c['admin']) || date('H') !== '12' || ($this->lastRunAt !== 0 && (time() - $this->lastRunAt) <= 4600)) {
                return;
            }

            $this->lastRunAt = time();
            $expiresAt = $this->bot->expireCert();

            if (empty($expiresAt) || $expiresAt - 60 * 60 * 24 * 14 >= time()) {
                return;
            }

            foreach ($c['admin'] as $admin) {
                $this->bot->send($admin, 'certificate expire: ' . date('Y-m-d H:i:s', $expiresAt));
            }
        } catch (Exception) {
        }
    }
}
