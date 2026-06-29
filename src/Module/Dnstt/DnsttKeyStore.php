<?php
declare(strict_types=1);

namespace VpnBot\Module\Dnstt;

use RuntimeException;

final class DnsttKeyStore
{
    public function __construct(
        private readonly string $directory = '/config/dnstt',
    ) {
    }

    /**
     * @return array{private:string,public:string}|false
     */
    public function load(): array|false
    {
        $privatePath = $this->privateKeyPath();
        $publicPath = $this->publicKeyPath();

        if (! file_exists($privatePath) || ! file_exists($publicPath)) {
            return false;
        }

        $private = file_get_contents($privatePath);
        $public = file_get_contents($publicPath);

        if ($private === false || $public === false) {
            throw new RuntimeException('Failed to read DNSTT key pair.');
        }

        return ['private' => $private, 'public' => $public];
    }

    /**
     * @param array{private:string,public:string} $pair
     */
    public function save(array $pair): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0777, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Failed to create DNSTT directory: %s', $this->directory));
        }

        if (file_put_contents($this->privateKeyPath(), $pair['private']) === false) {
            throw new RuntimeException('Failed to write DNSTT private key.');
        }

        if (file_put_contents($this->publicKeyPath(), $pair['public']) === false) {
            throw new RuntimeException('Failed to write DNSTT public key.');
        }
    }

    public function privateKeyPath(): string
    {
        return $this->directory . '/server.key';
    }

    public function publicKeyPath(): string
    {
        return $this->directory . '/server.pub';
    }
}
