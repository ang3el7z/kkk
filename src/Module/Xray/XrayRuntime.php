<?php

declare(strict_types=1);

namespace VpnBot\Module\Xray;

interface XrayRuntime
{
    /**
     * @param array<string, mixed> $config
     */
    public function apply(array $config, bool $restart): void;
}
