<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Docker;

final class DockerApiClient
{
    /**
     * @param array<string, mixed> $data
     * @return array<int|string, mixed>|null
     */
    public function request(string $url, string $method = 'GET', array $data = []): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => ! empty($data) ? json_encode($data) : null,
            CURLOPT_URL => "http://localhost$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock',
        ]);

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $response;
    }
}
