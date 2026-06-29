<?php

declare(strict_types=1);

namespace VpnBot\Module\AdGuard;

use RuntimeException;

final class AdGuardConfigStore implements AdGuardConfigRepository
{
    public function __construct(
        private readonly string $path = '/config/AdGuardHome.yaml',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (! function_exists('yaml_parse_file')) {
            throw new RuntimeException('The yaml extension is required to load AdGuard config files.');
        }

        $config = \yaml_parse_file($this->path);

        if (! is_array($config)) {
            throw new RuntimeException(sprintf('Failed to parse AdGuard config: %s', $this->path));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function save(array $config): void
    {
        if (! function_exists('yaml_emit_file')) {
            throw new RuntimeException('The yaml extension is required to save AdGuard config files.');
        }

        if (! \yaml_emit_file($this->path, $config)) {
            throw new RuntimeException(sprintf('Failed to write AdGuard config: %s', $this->path));
        }
    }
}
