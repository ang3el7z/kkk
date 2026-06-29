<?php

declare(strict_types=1);

namespace VpnBot\Module\NaiveProxy;

use RuntimeException;

final class CaddyfileStore
{
    public function __construct(
        private readonly string $path = '/config/Caddyfile',
    ) {
    }

    public function load(): string
    {
        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read Caddyfile: %s', $this->path));
        }

        return $contents;
    }

    public function save(string $contents): void
    {
        if (file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException(sprintf('Failed to write Caddyfile: %s', $this->path));
        }
    }
}
