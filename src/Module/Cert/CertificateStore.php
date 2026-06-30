<?php
declare(strict_types=1);

namespace VpnBot\Module\Cert;

use RuntimeException;

final class CertificateStore
{
    public function __construct(
        private readonly string $privatePath = '/certs/cert_private',
        private readonly string $publicPath = '/certs/cert_public',
    ) {
    }

    /**
     * @return array{private:string,public:string}|false
     */
    public function load(): array|false
    {
        if (! file_exists($this->privatePath) || ! file_exists($this->publicPath)) {
            return false;
        }

        $private = file_get_contents($this->privatePath);
        $public = file_get_contents($this->publicPath);

        if ($private === false || $public === false) {
            throw new RuntimeException('Failed to read certificate pair.');
        }

        if (preg_match('~BEGIN PRIVATE KEY~', $private) !== 1) {
            return false;
        }

        return ['private' => $private, 'public' => $public];
    }

    /**
     * @param array{private:string,public:string} $pair
     */
    public function save(array $pair): void
    {
        if (file_put_contents($this->privatePath, $pair['private']) === false) {
            throw new RuntimeException('Failed to write private certificate bundle.');
        }

        if (file_put_contents($this->publicPath, $pair['public']) === false) {
            throw new RuntimeException('Failed to write public certificate bundle.');
        }
    }

    public function delete(): void
    {
        if (file_exists($this->privatePath)) {
            unlink($this->privatePath);
        }

        if (file_exists($this->publicPath)) {
            unlink($this->publicPath);
        }
    }

    public function publicCertificate(): string
    {
        $contents = file_get_contents($this->publicPath);

        if ($contents === false) {
            throw new RuntimeException('Failed to read public certificate bundle.');
        }

        return $contents;
    }
}
