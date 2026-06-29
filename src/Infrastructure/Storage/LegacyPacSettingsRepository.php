<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Storage;

use RuntimeException;
use VpnBot\Domain\Settings\SettingsRepository;

final class LegacyPacSettingsRepository implements SettingsRepository
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $settings = $this->all();
        $settings[$key] = $value;
        $this->replaceAll($settings);
    }

    public function all(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read pac settings: %s', $this->path));
        }

        $decoded = json_decode($contents, true);

        if ($decoded === null && trim($contents) !== 'null' && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Failed to decode pac settings: %s', $this->path));
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function replaceAll(array $settings): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create pac settings directory: %s', $directory));
        }

        $encoded = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode pac settings.');
        }

        if (file_put_contents($this->path, $encoded) === false) {
            throw new RuntimeException(sprintf('Failed to write pac settings: %s', $this->path));
        }
    }
}
