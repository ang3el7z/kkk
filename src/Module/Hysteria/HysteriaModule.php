<?php
declare(strict_types=1);

namespace VpnBot\Module\Hysteria;

final class HysteriaModule
{
    public function __construct(
        private readonly HysteriaConfigStore $store,
        private readonly HysteriaRuntime $runtime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function loadConfig(): array
    {
        return $this->store->load();
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function syncPassword(array $config, string $password): array
    {
        if (! isset($config['auth']) || ! is_array($config['auth'])) {
            $config['auth'] = ['type' => 'password'];
        }

        $config['auth']['password'] = $password;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveAndRestart(array $config, bool $shouldStart): void
    {
        $this->store->save($config);
        $this->runtime->stop();

        if ($shouldStart) {
            $this->runtime->start();
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function extractPassword(array $config): string
    {
        $auth = $config['auth'] ?? null;

        if (! is_array($auth)) {
            return '';
        }

        return (string) ($auth['password'] ?? '');
    }
}
