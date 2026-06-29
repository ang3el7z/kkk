<?php
declare(strict_types=1);

namespace VpnBot\Module\Shadowsocks;

use RuntimeException;

final class ShadowsocksConfigStore
{
    public function __construct(
        private readonly string $serverPath = '/config/ssserver.json',
        private readonly string $localPath = '/config/sslocal.json',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function loadServer(): array
    {
        return $this->decode($this->serverPath);
    }

    /**
     * @return array<string, mixed>
     */
    public function loadLocal(): array
    {
        return $this->decode($this->localPath);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveServer(array $config): void
    {
        $this->encode($this->serverPath, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveLocal(array $config): void
    {
        $this->encode($this->localPath, $config);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read Shadowsocks config: %s', $path));
        }

        $config = json_decode($contents, true);

        if (! is_array($config)) {
            throw new RuntimeException(sprintf('Failed to decode Shadowsocks config: %s', $path));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function encode(string $path, array $config): void
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json) === false) {
            throw new RuntimeException(sprintf('Failed to write Shadowsocks config: %s', $path));
        }
    }
}
