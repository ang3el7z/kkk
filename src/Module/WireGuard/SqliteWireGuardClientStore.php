<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

use PDO;

final class SqliteWireGuardClientStore implements WireGuardClientStore
{
    public function __construct(
        private readonly PDO $connection,
        private readonly string $service,
    ) {
    }

    public function readAll(): array
    {
        $statement = $this->connection->prepare(
            'SELECT config_json FROM wireguard_clients WHERE instance_id = :instance_id ORDER BY id ASC'
        );
        $statement->execute(['instance_id' => $this->resolveInstanceId()]);
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        $clients = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row, true);

            if (is_array($decoded)) {
                $clients[] = $decoded;
            }
        }

        return $clients;
    }

    public function saveAll(array $clients): void
    {
        $instanceId = $this->resolveInstanceId();
        $timestamp = gmdate('c');
        $statement = $this->connection->prepare(
            'INSERT INTO wireguard_clients (instance_id, name, enabled, config_json, created_at, updated_at)
             VALUES (:instance_id, :name, :enabled, :config_json, :created_at, :updated_at)'
        );

        $this->connection->beginTransaction();

        try {
            $delete = $this->connection->prepare('DELETE FROM wireguard_clients WHERE instance_id = :instance_id');
            $delete->execute(['instance_id' => $instanceId]);

            foreach (array_values($clients) as $client) {
                $statement->execute([
                    'instance_id' => $instanceId,
                    'name' => $this->extractClientName($client),
                    'enabled' => empty($client['off']) ? 1 : 0,
                    'config_json' => json_encode($client, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    private function resolveInstanceId(): int
    {
        $select = $this->connection->prepare('SELECT id FROM wireguard_instances WHERE service = :service LIMIT 1');
        $select->execute(['service' => $this->service]);
        $instanceId = $select->fetchColumn();

        if ($instanceId !== false) {
            return (int) $instanceId;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO wireguard_instances (service, title, amnezia_enabled, dns, mtu, endpoint_mode)
             VALUES (:service, :title, 0, NULL, NULL, :endpoint_mode)'
        );
        $insert->execute([
            'service' => $this->service,
            'title' => $this->service === 'wg1' ? 'WireGuard 2' : 'WireGuard',
            'endpoint_mode' => 'domain',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $client
     */
    private function extractClientName(array $client): string
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
}
