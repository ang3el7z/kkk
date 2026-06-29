<?php

declare(strict_types=1);

namespace VpnBot\Module\NaiveProxy;

final class NaiveProxyModule
{
    public function __construct(
        private readonly CaddyfileStore $store,
        private readonly NaiveProxyRuntime $runtime,
    ) {
    }

    public function loadConfig(): string
    {
        return $this->store->load();
    }

    public function saveAndRestart(string $config, bool $shouldStart): void
    {
        $this->store->save($config);
        $this->runtime->stop();

        if ($shouldStart) {
            $this->runtime->start();
        }
    }

    public function updateBasicAuth(string $config, string $user, string $password): string
    {
        return preg_replace(
            '~^(\t+)?basic_auth[^\n]+~sm',
            '$1basic_auth ' . ($user !== '' ? $user : '_') . ' ' . ($password !== '' ? $password : '__'),
            $config
        ) ?? $config;
    }

    /**
     * @return array{user:string,password:string}
     */
    public function parseCredentials(string $config): array
    {
        preg_match('~^\s*basic_auth\s+(\S+)\s+(\S+)~sm', $config, $matches);

        return [
            'user' => $matches[1] ?? '_',
            'password' => $matches[2] ?? '__',
        ];
    }
}
