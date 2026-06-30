<?php

declare(strict_types=1);

namespace VpnBot\Telegram;

final class MenuActionHandler
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function menu(?string $type = null, ?string $arg = null): bool
    {
        $this->bot->menu(type: $type ?: false, arg: $arg ?: false);

        return true;
    }

    public function mirror(): bool
    {
        $this->bot->menu('mirror');

        return true;
    }
}
