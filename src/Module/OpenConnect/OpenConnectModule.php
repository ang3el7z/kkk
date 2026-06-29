<?php

declare(strict_types=1);

namespace VpnBot\Module\OpenConnect;

final class OpenConnectModule
{
    public function __construct(
        private readonly OpenConnectConfigStore $store,
        private readonly OpenConnectRuntime $runtime,
    ) {
    }

    public function loadConfig(): string
    {
        return $this->store->loadConfig();
    }

    /**
     * @return list<string>
     */
    public function loadUsers(): array
    {
        return $this->store->loadUsers();
    }

    public function saveAndRestart(string $config, bool $shouldStart): void
    {
        $this->store->saveConfig($config);
        $this->runtime->stop();

        if ($shouldStart) {
            $this->runtime->start();
        }
    }

    public function updateDns(string $config, string $dns): string
    {
        return preg_replace('~^dns[^\n]+~sm', "dns = $dns", $config) ?? $config;
    }

    public function updateCamouflageSecret(string $config, string $secret): string
    {
        return preg_replace('~^camouflage_secret[^\n]+~sm', 'camouflage_secret = "' . $secret . '"', $config) ?? $config;
    }

    public function updateDefaultDomain(string $config, string $domain): string
    {
        return preg_replace('~^default-domain[^\n]+~sm', "default-domain = $domain", $config) ?? $config;
    }

    public function toggleExposeIRoutes(string $config): string
    {
        preg_match('~^expose-iroutes = ([^\n]+)~sm', $config, $matches);
        $current = $matches[1] ?? 'false';

        return preg_replace('~^expose-iroutes[^\n]+~sm', 'expose-iroutes = ' . ($current === 'true' ? 'false' : 'true'), $config) ?? $config;
    }

    /**
     * @param list<string> $routes
     */
    public function applyRoutes(string $config, array $routes): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $config) ?: [];
        $filtered = array_values(array_filter($lines, static fn (string $line): bool => ! preg_match('~^(route|no-route) = ~', $line)));
        $output = [];
        $inserted = false;

        foreach ($filtered as $line) {
            $output[] = $line;

            if (! $inserted && str_starts_with($line, 'dns = ')) {
                foreach ($routes as $route) {
                    $output[] = 'route = ' . $route;
                }
                $inserted = true;
            }
        }

        if (! $inserted) {
            foreach ($routes as $route) {
                $output[] = 'route = ' . $route;
            }
        }

        return implode("\n", $output);
    }

    /**
     * @return array{camouflage_secret: string, dns: string, expose_iroutes: bool}
     */
    public function parseMenuState(string $config): array
    {
        preg_match('~^camouflage_secret[^\n]+?"([^"]*)"?~sm', $config, $camouflage);
        preg_match('~^dns = ([^\n]+)~sm', $config, $dns);
        preg_match('~^expose-iroutes = (true)~sm', $config, $expose);

        return [
            'camouflage_secret' => $camouflage[1] ?? '',
            'dns' => $dns[1] ?? '',
            'expose_iroutes' => ($expose[1] ?? '') === 'true',
        ];
    }

    /**
     * @param list<string> $users
     */
    public function syncAllUserPasswords(array $users, string $password): void
    {
        foreach ($users as $user) {
            $this->runtime->setUserPassword($user, $password);
        }
    }

    public function addUser(string $username, string $password): void
    {
        $this->runtime->setUserPassword($username, $password);
    }

    public function deleteUser(string $username): void
    {
        $this->runtime->deleteUser($username);
    }
}
