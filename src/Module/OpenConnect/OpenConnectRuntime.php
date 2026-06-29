<?php

declare(strict_types=1);

namespace VpnBot\Module\OpenConnect;

interface OpenConnectRuntime
{
    public function start(): string;

    public function stop(): string;

    public function setUserPassword(string $username, string $password): string;

    public function deleteUser(string $username): string;
}
