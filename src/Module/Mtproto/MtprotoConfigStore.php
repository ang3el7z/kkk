<?php
declare(strict_types=1);

namespace VpnBot\Module\Mtproto;

use RuntimeException;

final class MtprotoConfigStore
{
    public function __construct(
        private readonly string $secretPath = '/config/mtprotosecret',
        private readonly string $domainPath = '/config/mtprotodomain',
        private readonly string $adtagPath = '/config/mtprotoadtag',
    ) {
    }

    /**
     * @return array{secret:string,domain:string,adtag:string}
     */
    public function load(): array
    {
        return [
            'secret' => $this->read($this->secretPath, false),
            'domain' => $this->read($this->domainPath, true),
            'adtag' => trim($this->read($this->adtagPath, true)),
        ];
    }

    /**
     * @param array{secret:string,domain:string,adtag:string} $config
     */
    public function save(array $config): void
    {
        $this->write($this->secretPath, $config['secret']);
        $this->write($this->domainPath, $config['domain']);
        $this->write($this->adtagPath, trim($config['adtag']));
    }

    private function read(string $path, bool $optional): string
    {
        if ($optional && ! file_exists($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            if ($optional) {
                return '';
            }

            throw new RuntimeException(sprintf('Failed to read MTProto config: %s', $path));
        }

        return $contents;
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Failed to write MTProto config: %s', $path));
        }
    }
}
