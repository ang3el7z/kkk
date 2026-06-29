<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Legacy;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Database\Migrator;
use VpnBot\Infrastructure\Database\SqliteFeatureRepository;

final class LegacyImporter
{
    public function __construct(
        private readonly PDO $connection,
        private readonly Migrator $migrator,
        private readonly FeatureRegistry $featureRegistry,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function import(string $configDirectory, string $appConfigPath): array
    {
        if (! is_dir($configDirectory)) {
            throw new InvalidArgumentException(sprintf('Legacy config directory not found: %s', $configDirectory));
        }

        $this->migrator->migrate();
        (new SqliteFeatureRepository($this->connection, $this->featureRegistry))->all();

        $timestamp = gmdate('c');
        $report = [
            'admins' => 0,
            'settings' => 0,
            'wireguard_instances' => 0,
            'wireguard_clients' => 0,
            'xray_users' => 0,
            'xray_stats' => 0,
            'features' => count($this->featureRegistry->all()),
        ];

        $this->connection->beginTransaction();

        try {
            $appConfig = $this->readPhpConfig($appConfigPath);
            $pacConfig = $this->readJsonFile($configDirectory . DIRECTORY_SEPARATOR . 'pac.json', []);
            $xrayConfig = $this->readJsonFile($configDirectory . DIRECTORY_SEPARATOR . 'xray.json', []);
            $xrayStats = $this->readJsonFile($configDirectory . DIRECTORY_SEPARATOR . 'xray.stats', []);

            $this->clearTables();

            $report['admins'] = $this->importAdmins($appConfig, $timestamp);
            $report['settings'] = $this->importSettings($appConfig, $pacConfig, $xrayConfig, $timestamp);
            $report['wireguard_instances'] = $this->importWireGuardData($configDirectory, $pacConfig, $timestamp, $report);
            $report['xray_users'] = $this->importXrayUsers($xrayConfig, $timestamp);
            $report['xray_stats'] = $this->importXrayStats($xrayStats, $timestamp);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $report;
    }

    private function clearTables(): void
    {
        foreach ([
            'admins',
            'settings',
            'wireguard_clients',
            'wireguard_instances',
            'xray_users',
            'xray_stats',
        ] as $table) {
            $this->connection->exec('DELETE FROM ' . $table);
        }
    }

    /**
     * @param array<string, mixed> $appConfig
     */
    private function importAdmins(array $appConfig, string $timestamp): int
    {
        $admins = $appConfig['admin'] ?? [];

        if (! is_array($admins)) {
            $admins = [$admins];
        }

        $admins = array_values(array_filter($admins, static fn (mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value))));

        if ($admins === []) {
            return 0;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO admins (telegram_id, username, created_at) VALUES (:telegram_id, :username, :created_at)'
        );

        foreach ($admins as $adminId) {
            $statement->execute([
                'telegram_id' => (int) $adminId,
                'username' => null,
                'created_at' => $timestamp,
            ]);
        }

        return count($admins);
    }

    /**
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $pacConfig
     * @param array<string, mixed> $xrayConfig
     */
    private function importSettings(array $appConfig, array $pacConfig, array $xrayConfig, string $timestamp): int
    {
        $settings = [
            'bot.key' => $appConfig['key'] ?? null,
            'bot.debug' => isset($appConfig['debug']) ? (bool) $appConfig['debug'] : null,
            'legacy.app_config' => $appConfig,
            'legacy.pac' => $pacConfig,
            'legacy.xray_config' => $xrayConfig,
        ];

        $statement = $this->connection->prepare(
            'INSERT INTO settings (key, value_json, updated_at) VALUES (:key, :value_json, :updated_at)'
        );

        $count = 0;

        foreach ($settings as $key => $value) {
            $statement->execute([
                'key' => $key,
                'value_json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'updated_at' => $timestamp,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $pacConfig
     * @param array<string, int> $report
     */
    private function importWireGuardData(string $configDirectory, array $pacConfig, string $timestamp, array &$report): int
    {
        $instances = [
            [
                'service' => 'wg',
                'title' => 'WireGuard',
                'amnezia_enabled' => ! empty($pacConfig['amnezia']),
                'dns' => $pacConfig['dns'] ?? null,
                'mtu' => $this->normalizeNullableInt($pacConfig['mtu'] ?? null),
                'endpoint_mode' => ! empty($pacConfig['endpoint']) ? 'ip' : 'domain',
                'clients_path' => $configDirectory . DIRECTORY_SEPARATOR . 'clients.json',
            ],
            [
                'service' => 'wg1',
                'title' => 'WireGuard 2',
                'amnezia_enabled' => ! empty($pacConfig['wg1_amnezia']),
                'dns' => $pacConfig['wg1_dns'] ?? null,
                'mtu' => $this->normalizeNullableInt($pacConfig['wg1_mtu'] ?? null),
                'endpoint_mode' => ! empty($pacConfig['wg1_endpoint']) ? 'ip' : 'domain',
                'clients_path' => $configDirectory . DIRECTORY_SEPARATOR . 'clients1.json',
            ],
        ];

        $instanceStatement = $this->connection->prepare(
            'INSERT INTO wireguard_instances (service, title, amnezia_enabled, dns, mtu, endpoint_mode) VALUES (:service, :title, :amnezia_enabled, :dns, :mtu, :endpoint_mode)'
        );
        $clientStatement = $this->connection->prepare(
            'INSERT INTO wireguard_clients (instance_id, name, enabled, config_json, created_at, updated_at) VALUES (:instance_id, :name, :enabled, :config_json, :created_at, :updated_at)'
        );

        $instanceCount = 0;
        $clientCount = 0;

        foreach ($instances as $instance) {
            $instanceStatement->execute([
                'service' => $instance['service'],
                'title' => $instance['title'],
                'amnezia_enabled' => $instance['amnezia_enabled'] ? 1 : 0,
                'dns' => $instance['dns'],
                'mtu' => $instance['mtu'],
                'endpoint_mode' => $instance['endpoint_mode'],
            ]);

            $instanceId = (int) $this->connection->lastInsertId();
            $instanceCount++;

            $clients = $this->readJsonFile($instance['clients_path'], []);

            if (! is_array($clients)) {
                continue;
            }

            foreach ($clients as $client) {
                if (! is_array($client)) {
                    continue;
                }

                $clientStatement->execute([
                    'instance_id' => $instanceId,
                    'name' => $this->extractWireGuardClientName($client),
                    'enabled' => empty($client['off']) ? 1 : 0,
                    'config_json' => json_encode($client, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $clientCount++;
            }
        }

        $report['wireguard_clients'] = $clientCount;

        return $instanceCount;
    }

    /**
     * @param array<string, mixed> $xrayConfig
     */
    private function importXrayUsers(array $xrayConfig, string $timestamp): int
    {
        $clients = $xrayConfig['inbounds'][0]['settings']['clients'] ?? [];

        if (! is_array($clients)) {
            return 0;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO xray_users (email, uuid, flow, enabled, expires_at, config_json, created_at, updated_at) VALUES (:email, :uuid, :flow, :enabled, :expires_at, :config_json, :created_at, :updated_at)'
        );

        $count = 0;

        foreach ($clients as $client) {
            if (! is_array($client) || empty($client['email']) || empty($client['id'])) {
                continue;
            }

            $statement->execute([
                'email' => (string) $client['email'],
                'uuid' => (string) $client['id'],
                'flow' => isset($client['flow']) ? (string) $client['flow'] : null,
                'enabled' => empty($client['off']) ? 1 : 0,
                'expires_at' => isset($client['time']) ? gmdate('c', (int) $client['time']) : null,
                'config_json' => json_encode($client, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $xrayStats
     */
    private function importXrayStats(array $xrayStats, string $timestamp): int
    {
        if ($xrayStats === []) {
            return 0;
        }

        $rows = [];

        foreach (['session', 'global'] as $scope) {
            if (! empty($xrayStats[$scope]) && is_array($xrayStats[$scope])) {
                $rows[] = $this->buildStatsRow($scope, 'system', $xrayStats[$scope], $timestamp);
            }
        }

        if (! empty($xrayStats['users']) && is_array($xrayStats['users'])) {
            foreach ($xrayStats['users'] as $index => $userStats) {
                if (! is_array($userStats)) {
                    continue;
                }

                foreach (['session', 'global'] as $period) {
                    if (! empty($userStats[$period]) && is_array($userStats[$period])) {
                        $rows[] = $this->buildStatsRow('user', (string) $index, $userStats[$period], $timestamp, $period);
                    }
                }
            }
        }

        if ($rows === []) {
            return 0;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO xray_stats (scope, subject, upload, download, period, updated_at) VALUES (:scope, :subject, :upload, :download, :period, :updated_at)'
        );

        foreach ($rows as $row) {
            $statement->execute($row);
        }

        return count($rows);
    }

    /**
     * @param array<string, mixed> $stats
     * @return array{scope: string, subject: string, upload: int, download: int, period: string, updated_at: string}
     */
    private function buildStatsRow(string $scope, string $subject, array $stats, string $timestamp, ?string $period = null): array
    {
        return [
            'scope' => $scope,
            'subject' => $subject,
            'upload' => (int) ($stats['upload'] ?? 0),
            'download' => (int) ($stats['download'] ?? 0),
            'period' => $period ?? $scope,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readPhpConfig(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $c = [];
        /** @noinspection PhpIncludeInspection */
        require $path;

        return is_array($c) ? $c : [];
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    private function readJsonFile(string $path, array $default): array
    {
        if (! is_file($path)) {
            return $default;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read legacy JSON file: %s', $path));
        }

        $decoded = json_decode($contents, true);

        if ($decoded === null && trim($contents) !== 'null' && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Failed to decode legacy JSON file: %s', $path));
        }

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * @param array<string, mixed> $client
     */
    private function extractWireGuardClientName(array $client): string
    {
        $interface = $client['interface'] ?? [];

        if (is_array($interface)) {
            foreach (['## name', '# name', 'name', 'Address'] as $key) {
                if (! empty($interface[$key]) && is_string($interface[$key])) {
                    return $interface[$key];
                }
            }
        }

        return 'client';
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
