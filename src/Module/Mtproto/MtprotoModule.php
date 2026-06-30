<?php
declare(strict_types=1);

namespace VpnBot\Module\Mtproto;

final class MtprotoModule
{
    public function __construct(
        private readonly MtprotoConfigStore $store,
        private readonly MtprotoRuntime $runtime,
    ) {
    }

    /**
     * @return array{secret:string,domain:string,adtag:string}
     */
    public function loadConfig(): array
    {
        return $this->store->load();
    }

    /**
     * @param array{secret:string,domain:string,adtag:string} $config
     */
    public function saveConfig(array $config): void
    {
        $this->store->save($config);
    }

    public function normalizeAdtag(string $adtag): ?string
    {
        $adtag = trim($adtag);

        if ($adtag === '0' || $adtag === '') {
            return '';
        }

        if (preg_match('~^[a-f0-9]{32}$~i', $adtag) !== 1) {
            return null;
        }

        return strtolower($adtag);
    }

    /**
     * @param array{secret:string,domain:string,adtag:string} $config
     */
    public function restart(array $config, string $publicIp): void
    {
        $this->runtime->stop();

        if (preg_match('~^\w{32}$~', $config['secret']) !== 1) {
            return;
        }

        $this->runtime->start($this->buildStartCommand($config, $publicIp));
    }

    /**
     * @param array{secret:string,domain:string,adtag:string} $config
     */
    public function buildLink(array $config, string $server, int $port): string
    {
        $encodedDomain = bin2hex(str_replace(["\r", "\n"], '', $config['domain'] !== '' ? $config['domain'] : 'yandex.ru'));

        return sprintf(
            'https://t.me/proxy?server=%s&port=%d&secret=ee%s%s',
            $server,
            $port,
            $config['secret'],
            $encodedDomain
        );
    }

    /**
     * @param array{secret:string,domain:string,adtag:string} $config
     * @return array{status:string,domain:string,adtag:string,link:?string}
     */
    public function buildMenuState(array $config, bool $isRunning, string $server, int $port): array
    {
        return [
            'status' => $isRunning ? 'on' : 'off',
            'domain' => $config['domain'] !== '' ? $config['domain'] : 'yandex.ru',
            'adtag' => $config['adtag'] !== '' ? $config['adtag'] : 'off',
            'link' => $isRunning ? $this->buildLink($config, $server, $port) : null,
        ];
    }

    /**
     * @param array{secret:string,domain:string,adtag:string} $config
     */
    private function buildStartCommand(array $config, string $publicIp): string
    {
        $domain = $config['domain'] !== '' ? $config['domain'] : 'yandex.ru';
        $proxyTag = $config['adtag'] !== '' ? ' -P ' . escapeshellarg($config['adtag']) : '';

        return sprintf(
            'mtproto-proxy --domain %s -u nobody -H 443 --nat-info 10.10.0.8:%s -S %s --aes-pwd /proxy-secret /proxy-multi.conf -M 1%s',
            $domain,
            $publicIp,
            $config['secret'],
            $proxyTag
        );
    }
}
