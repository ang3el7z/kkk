<?php

declare(strict_types=1);

namespace VpnBot\Telegram;

final class SettingsActionHandler
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function featureToggle(string $featureId): bool
    {
        $this->bot->featureToggle($featureId);

        return true;
    }

    public function featureToggleConfirm(string $featureId, string $action): bool
    {
        $this->bot->featureToggleConfirm($featureId, $action);

        return true;
    }

    public function ports(): bool
    {
        $this->bot->ports();

        return true;
    }

    public function changePort(?string $container = null): bool
    {
        $this->bot->changePort($container);

        return true;
    }
}
