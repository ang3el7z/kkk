<?php

declare(strict_types=1);

namespace VpnBot\Module\AdGuard;

final class AdGuardModule
{
    public function __construct(
        private readonly AdGuardConfigRepository $configStore,
        private readonly AdGuardRuntime $runtime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function loadConfig(): array
    {
        return $this->configStore->load();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveConfig(array $config, bool $restart = false): void
    {
        if ($restart) {
            $this->runtime->stop();
        }

        $this->configStore->save($config);

        if ($restart) {
            $this->runtime->start();
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function syncPasswordAndTls(array $config, string $password, bool $enableTls, ?string $domain): array
    {
        $config['users'][0]['password'] = password_hash($password, PASSWORD_DEFAULT);

        if ($enableTls && $domain !== null && $domain !== '') {
            $config['tls']['enabled'] = true;
            $config['tls']['server_name'] = $domain;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, array<string, mixed>> $xrayClients
     */
    public function syncXrayClients(array $config, array $xrayClients): array
    {
        $persistent = [];

        foreach ($xrayClients as $client) {
            if (! is_array($client)) {
                continue;
            }

            $persistent[] = [
                'safe_search' => [
                    'enabled' => true,
                    'bing' => true,
                    'duckduckgo' => true,
                    'google' => true,
                    'pixabay' => true,
                    'yandex' => true,
                    'youtube' => true,
                ],
                'blocked_services' => [
                    'schedule' => ['time_zone' => date_default_timezone_get()],
                    'ids' => [],
                ],
                'name' => (string) ($client['email'] ?? $client['id'] ?? 'client'),
                'ids' => [(string) ($client['id'] ?? '')],
                'tags' => [],
                'upstreams' => [],
                'uid' => (string) ($client['id'] ?? ''),
                'upstreams_cache_size' => 0,
                'upstreams_cache_enabled' => false,
                'use_global_settings' => true,
                'filtering_enabled' => false,
                'parental_enabled' => false,
                'safebrowsing_enabled' => false,
                'use_global_blocked_services' => true,
                'ignore_querylog' => false,
                'ignore_statistics' => false,
            ];
        }

        $config['clients']['persistent'] = $persistent;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, string> $entries
     */
    public function setAllowedClients(array $config, array $entries, bool $delete): array
    {
        if ($delete) {
            unset($config['dns']['allowed_clients']);

            return $config;
        }

        $config['dns']['allowed_clients'] = array_values(array_unique(array_filter($entries, static fn (string $entry): bool => $entry !== '')));

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addUpstream(array $config, string $upstream): array
    {
        $config['dns']['upstream_dns'] ??= [];
        $config['dns']['upstream_dns'][] = $upstream;
        $config['dns']['upstream_dns'] = array_values($config['dns']['upstream_dns']);

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function removeUpstream(array $config, int $index): array
    {
        if (! empty($config['dns']['upstream_dns'][$index])) {
            unset($config['dns']['upstream_dns'][$index]);
            $config['dns']['upstream_dns'] = array_values($config['dns']['upstream_dns']);
        }

        return $config;
    }
}
