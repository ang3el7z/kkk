<?php

declare(strict_types=1);

namespace VpnBot\Module\Xray;

use PDO;
use VpnBot\Domain\Settings\SettingsRepository;

final class SqliteXrayStateRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadTemplate(): ?array
    {
        $config = $this->settingsRepository->get('legacy.xray_config');

        return is_array($config) ? $config : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveTemplate(array $config): void
    {
        $this->settingsRepository->set('legacy.xray_config', $config);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadUsers(): array
    {
        $statement = $this->connection->query('SELECT config_json FROM xray_users ORDER BY id ASC');
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        $users = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row, true);

            if (is_array($decoded)) {
                $users[] = $decoded;
            }
        }

        return $users;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    public function saveUsers(array $users): void
    {
        $timestamp = gmdate('c');
        $statement = $this->connection->prepare(
            'INSERT INTO xray_users (email, uuid, flow, enabled, expires_at, config_json, created_at, updated_at)
             VALUES (:email, :uuid, :flow, :enabled, :expires_at, :config_json, :created_at, :updated_at)'
        );

        $this->connection->beginTransaction();

        try {
            $this->connection->exec('DELETE FROM xray_users');

            foreach ($users as $user) {
                $statement->execute([
                    'email' => (string) ($user['email'] ?? 'user'),
                    'uuid' => (string) ($user['id'] ?? ''),
                    'flow' => isset($user['flow']) ? (string) $user['flow'] : null,
                    'enabled' => empty($user['off']) ? 1 : 0,
                    'expires_at' => isset($user['time']) ? gmdate('c', (int) $user['time']) : null,
                    'config_json' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
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

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    public function loadStats(): array
    {
        $statement = $this->connection->query('SELECT scope, subject, upload, download, period FROM xray_stats ORDER BY id ASC');
        $rows = $statement->fetchAll();

        if ($rows === []) {
            return [];
        }

        $stats = [
            'global' => ['upload' => 0, 'download' => 0],
            'session' => ['upload' => 0, 'download' => 0],
            'users' => [],
        ];

        foreach ($rows as $row) {
            if (($row['scope'] ?? null) === 'user') {
                $subject = (string) $row['subject'];
                $period = (string) $row['period'];
                $stats['users'][(int) $subject][$period] = [
                    'upload' => (int) $row['upload'],
                    'download' => (int) $row['download'],
                ];
                continue;
            }

            $period = (string) $row['period'];
            $stats[$period] = [
                'upload' => (int) $row['upload'],
                'download' => (int) $row['download'],
            ];
        }

        ksort($stats['users']);

        return $stats;
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $stats
     */
    public function saveStats(array $stats): void
    {
        $timestamp = gmdate('c');
        $statement = $this->connection->prepare(
            'INSERT INTO xray_stats (scope, subject, upload, download, period, updated_at)
             VALUES (:scope, :subject, :upload, :download, :period, :updated_at)'
        );

        $rows = [];

        foreach (['global', 'session'] as $period) {
            $values = $stats[$period] ?? null;

            if (is_array($values)) {
                $rows[] = [
                    'scope' => 'system',
                    'subject' => 'system',
                    'upload' => (int) ($values['upload'] ?? 0),
                    'download' => (int) ($values['download'] ?? 0),
                    'period' => $period,
                    'updated_at' => $timestamp,
                ];
            }
        }

        $users = $stats['users'] ?? [];

        if (is_array($users)) {
            foreach ($users as $index => $periods) {
                if (! is_array($periods)) {
                    continue;
                }

                foreach (['global', 'session'] as $period) {
                    $values = $periods[$period] ?? null;

                    if (! is_array($values)) {
                        continue;
                    }

                    $rows[] = [
                        'scope' => 'user',
                        'subject' => (string) $index,
                        'upload' => (int) ($values['upload'] ?? 0),
                        'download' => (int) ($values['download'] ?? 0),
                        'period' => $period,
                        'updated_at' => $timestamp,
                    ];
                }
            }
        }

        $this->connection->beginTransaction();

        try {
            $this->connection->exec('DELETE FROM xray_stats');

            foreach ($rows as $row) {
                $statement->execute($row);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }
}
