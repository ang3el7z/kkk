<?php
declare(strict_types=1);

namespace VpnBot\Module\Shadowsocks;

final class ShadowsocksModule
{
    public function __construct(
        private readonly ShadowsocksConfigStore $store,
        private readonly ShadowsocksRuntime $runtime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function loadServerConfig(): array
    {
        return $this->store->loadServer();
    }

    /**
     * @return array<string, mixed>
     */
    public function loadLocalConfig(): array
    {
        return $this->store->loadLocal();
    }

    /**
     * @param array<string, mixed> $serverConfig
     * @param array<string, mixed> $localConfig
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function syncPassword(array $serverConfig, array $localConfig, string $password): array
    {
        $serverConfig['password'] = $password;
        $localConfig['password'] = $password;

        return [$serverConfig, $localConfig];
    }

    /**
     * @param array<string, mixed> $serverConfig
     * @param array<string, mixed> $localConfig
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function toggleV2rayPlugin(
        array $serverConfig,
        array $localConfig,
        string $domain,
        bool $sslEnabled,
        int $serverPort,
    ): array {
        if (! empty($serverConfig['plugin'])) {
            unset($serverConfig['plugin'], $serverConfig['plugin_opts'], $localConfig['plugin'], $localConfig['plugin_opts']);
            $localConfig['server'] = 'ss';
            $localConfig['server_port'] = $serverPort;
            $serverConfig['server_port'] = $serverPort;

            return [$serverConfig, $localConfig];
        }

        $serverConfig['plugin'] = 'v2ray-plugin';
        $serverConfig['plugin_opts'] = 'server;loglevel=none';
        $localConfig['server'] = 'up';
        $localConfig['server_port'] = $sslEnabled ? 443 : 80;
        $localConfig['plugin'] = 'v2ray-plugin';
        $localConfig['plugin_opts'] = ($sslEnabled ? 'tls;' : '') . "fast-open;path=/v2ray;host=$domain";

        return [$serverConfig, $localConfig];
    }

    /**
     * @param array<string, mixed> $serverConfig
     * @return array{link:string,port:int,options:string,plugin_enabled:bool}
     */
    public function buildConnectionDetails(
        array $serverConfig,
        string $domain,
        int $serverPort,
        string $hash,
        bool $sslEnabled,
    ): array {
        $pluginEnabled = ! empty($serverConfig['plugin']);
        $path = '/v2ray' . $hash;
        $publicPort = $pluginEnabled ? ($sslEnabled ? 443 : 80) : $serverPort;
        $pluginOptions = "path=$path;host=$domain" . ($sslEnabled ? ';tls' : '');
        $options = ($sslEnabled ? 'tls;' : '') . "fast-open;path=$path;host=$domain";
        $plugin = $pluginEnabled ? '?plugin=' . urlencode('v2ray-plugin;' . $pluginOptions) : '';
        $link = preg_replace('~==~', '', 'ss://' . base64_encode((string) $serverConfig['method'] . ':' . (string) $serverConfig['password'])) . "@$domain:$publicPort" . $plugin;

        return [
            'link' => $link,
            'port' => $publicPort,
            'options' => $options,
            'plugin_enabled' => $pluginEnabled,
        ];
    }

    /**
     * @param array<string, mixed> $serverConfig
     * @param array<string, mixed> $localConfig
     */
    public function saveAndRestart(array $serverConfig, array $localConfig): void
    {
        $this->store->saveServer($serverConfig);
        $this->store->saveLocal($localConfig);
        $this->runtime->stopLocal();
        $this->runtime->stopServer();
        $this->runtime->startServer();
        $this->runtime->startLocal();
    }

    /**
     * @param array<string, mixed> $serverConfig
     */
    public function saveServerAndRestart(array $serverConfig): void
    {
        $this->store->saveServer($serverConfig);
        $this->runtime->stopServer();
        $this->runtime->startServer();
    }

    /**
     * @param array<string, mixed> $localConfig
     */
    public function saveLocalAndRestart(array $localConfig): void
    {
        $this->store->saveLocal($localConfig);
        $this->runtime->stopLocal();
        $this->runtime->startLocal();
    }
}
