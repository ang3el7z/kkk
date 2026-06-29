<?php

declare(strict_types=1);

namespace VpnBot\Domain\Settings;

interface SettingsRepository
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * @param array<string, mixed> $settings
     */
    public function replaceAll(array $settings): void;
}
