<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

use Exception;

final class VersionCheckAction implements CronAction
{
    private int $lastRunAt = 0;
    private string $lastSeenVersion = '';

    public function __construct(
        private readonly object $bot,
        private readonly string $configPath = __DIR__ . '/../../../app/config.php',
    ) {
    }

    public function tick(): void
    {
        try {
            require $this->configPath;

            if (empty($c['admin']) || ($this->lastRunAt !== 0 && (time() - $this->lastRunAt) <= 3600)) {
                return;
            }

            $this->lastRunAt = time();
            $current = file_get_contents('/version');
            $branch = trim((string) exec('git -C / rev-parse --abbrev-ref HEAD'));
            $last = file_get_contents("https://raw.githubusercontent.com/mercurykd/vpnbot/$branch/version");

            if (empty($last) || $last === $this->lastSeenVersion || $last === $current) {
                return;
            }

            $this->lastSeenVersion = $last;
            $diff = array_slice(explode("\n", $last), 0, count(explode("\n", $last)) - count(explode("\n", (string) $current)));
            $diff = array_slice($diff, 0, 10);

            if ($diff === []) {
                return;
            }

            exec('git -C / fetch');

            foreach ($c['admin'] as $admin) {
                $this->bot->send($admin, implode("\n", $diff), 0, [[
                    [
                        'text' => 'changelog',
                        'web_app' => ['url' => "https://raw.githubusercontent.com/mercurykd/vpnbot/$branch/version"],
                    ],
                    [
                        'text' => $this->bot->i18n('update bot'),
                        'callback_data' => '/applyupdatebot',
                    ],
                ]]);
            }

            if ($this->bot->getPacConf()['autoupdate']) {
                $this->bot->input['chat'] = $this->bot->input['from'] = $c['admin'][0];
                $this->bot->applyupdatebot();
            }
        } catch (Exception) {
        }
    }
}
