<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Database;

use PDO;

final class SqliteAuditLogWriter
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(?int $actorId, string $action, array $payload): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO audit_log (actor_id, action, payload_json, created_at) VALUES (:actor_id, :action, :payload_json, :created_at)'
        );

        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => gmdate('c'),
        ]);
    }
}
