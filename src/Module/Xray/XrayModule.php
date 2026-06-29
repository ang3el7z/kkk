<?php

declare(strict_types=1);

namespace VpnBot\Module\Xray;

use RuntimeException;

final class XrayModule
{
    public function __construct(
        private readonly XrayConfigCodec $codec,
        private readonly SqliteXrayStateRepository $repository,
        private readonly XrayRuntime $runtime,
        private readonly string $configPath = '/config/xray.json',
        private readonly string $statsPath = '/config/xray.stats',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $config = $this->repository->loadTemplate() ?? $this->readJsonFile($this->configPath);
        $users = $this->repository->loadUsers();

        if ($users !== []) {
            $config['inbounds'][0]['settings']['clients'] = $users;
        }

        return $this->codec->normalize($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveConfig(array $config, bool $restart = true): void
    {
        $normalized = $this->codec->normalize($config);
        $users = $normalized['inbounds'][0]['settings']['clients'] ?? [];
        $this->repository->saveTemplate($normalized);
        $this->repository->saveUsers(is_array($users) ? array_values($users) : []);
        $this->runtime->apply($normalized, $restart);
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    public function getStats(): array
    {
        $stats = $this->repository->loadStats();

        if ($stats !== []) {
            return $stats;
        }

        return $this->readJsonFile($this->statsPath, []);
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $stats
     */
    public function saveStats(array $stats): void
    {
        $this->repository->saveStats($stats);
    }

    public function buildLink(int $index, array $pacConfig, string $domain, string $hash, mixed $certType, int|false $style = false): string
    {
        $config = $this->getConfig();
        $client = $config['inbounds'][0]['settings']['clients'][$index] ?? null;

        if (! is_array($client)) {
            throw new RuntimeException(sprintf('Xray client not found at index %d.', $index));
        }

        $scheme = empty($certType) ? 'http' : 'https';
        $clientId = (string) ($client['id'] ?? '');
        $email = (string) ($client['email'] ?? $clientId);
        $subscriptionSing = $scheme . '://' . $domain . '/pac' . $hash . '/' . base64_encode(serialize([
            'h' => $hash,
            't' => 'si',
            's' => $clientId,
        ]));
        $subscriptionV2 = $scheme . '://' . $domain . '/pac' . $hash . '/' . base64_encode(serialize([
            'h' => $hash,
            't' => 's',
            's' => $clientId,
        ]));

        if ($style === 1) {
            return 'v2rayng://install-config?url=' . $subscriptionV2 . '#' . $clientId;
        }

        if ($style === 2) {
            return 'sing-box://import-remote-profile/?url=' . $subscriptionSing . '#' . $email;
        }

        $transport = (string) ($pacConfig['transport'] ?? 'Websocket');
        $inbound = $config['inbounds'][0];

        return match ($transport) {
            'Reality' => 'vless://' . $clientId . '@' . $domain . ':443'
                . '?security=reality'
                . '&sni=' . urlencode((string) ($inbound['streamSettings']['realitySettings']['serverNames'][0] ?? $domain))
                . '&fp=chrome&pbk=' . urlencode((string) ($pacConfig['xray'] ?? ''))
                . '&sid=' . urlencode((string) ($inbound['streamSettings']['realitySettings']['shortIds'][0] ?? ''))
                . '&type=tcp'
                . '&flow=xtls-rprx-vision'
                . '#' . $email,
            'xhttp' => 'vless://' . $clientId . '@' . $domain . ':443'
                . '?security=tls'
                . '&type=xhttp'
                . '&headerType='
                . '&path=%2Fws' . $hash
                . '&host=' . $domain
                . '&flow='
                . '&mode=packet-up'
                . '&extra=%7B%22xmux%22%3A%7B%22cMaxReuseTimes%22%3A0%2C%22maxConcurrency%22%3A%2216-32%22%2C%22maxConnections%22%3A0%2C%22hKeepAlivePeriod%22%3A0%2C%22hMaxRequestTimes%22%3A%22600-900%22%2C%22hMaxReusableSecs%22%3A%221800-3000%22%7D%2C%22headers%22%3A%7B%7D%2C%22noGRPCHeader%22%3Afalse%2C%22xPaddingBytes%22%3A%22100-1000%22%2C%22scMaxEachPostBytes%22%3A1000000%2C%22scMinPostsIntervalMs%22%3A30%2C%22scStreamUpServerSecs%22%3A%2220-80%22%7D'
                . '&sni=' . $domain
                . '&fp=chrome'
                . '&alpn=h2'
                . '#' . $email,
            default => 'vless://' . $clientId . '@' . $domain . ':443'
                . '?flow='
                . '&path=%2Fws' . $hash
                . '&security=tls'
                . '&sni=' . $domain
                . '&fp=chrome'
                . '&type=ws'
                . '#' . $email,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path, array $default = null): array
    {
        if (! is_file($path)) {
            return $default ?? [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read Xray JSON file: %s', $path));
        }

        return $this->codec->parse($contents);
    }
}
