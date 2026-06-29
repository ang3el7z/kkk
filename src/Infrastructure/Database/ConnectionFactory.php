<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Database;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    public function create(string $databasePath): PDO
    {
        if (! extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('The pdo_sqlite extension is required to use the SQLite storage.');
        }

        $directory = dirname($databasePath);

        if ($directory !== '' && $directory !== '.' && ! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create database directory: %s', $directory));
        }

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
