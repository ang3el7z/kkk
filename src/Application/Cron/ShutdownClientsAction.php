<?php
declare(strict_types=1);

namespace VpnBot\Application\Cron;

final class ShutdownClientsAction implements CronAction
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function tick(): void
    {
        $this->bot->shutdownClient();
        $this->bot->shutdownClientXr();
    }
}
