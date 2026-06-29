<?php

declare(strict_types=1);

namespace VpnBot\Module\Xray;

use RuntimeException;
use stdClass;

final class XrayConfigCodec
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Failed to decode Xray config JSON.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function encode(array $config): string
    {
        $encoded = json_encode(
            $this->normalize($config),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode Xray config JSON.');
        }

        return $encoded;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function normalize(array $config): array
    {
        $config['inbounds'] = is_array($config['inbounds'] ?? null) ? array_values($config['inbounds']) : [];

        if ($config['inbounds'] === []) {
            $config['inbounds'][] = [
                'port' => 443,
                'protocol' => 'vless',
                'settings' => [
                    'clients' => [],
                    'decryption' => 'none',
                ],
                'streamSettings' => [
                    'network' => 'ws',
                    'wsSettings' => ['path' => '/ws'],
                ],
                'tag' => 'vless_tls',
            ];
        }

        if (! isset($config['inbounds'][0]['settings']) || ! is_array($config['inbounds'][0]['settings'])) {
            $config['inbounds'][0]['settings'] = [];
        }

        $clients = $config['inbounds'][0]['settings']['clients'] ?? [];
        $config['inbounds'][0]['settings']['clients'] = is_array($clients) ? array_values($clients) : [];
        $config['log']['access'] = '/logs/xray';

        if (! $this->hasInboundTag($config, 'api')) {
            $config['inbounds'][] = [
                'listen' => '127.0.0.1',
                'port' => 8080,
                'protocol' => 'dokodemo-door',
                'settings' => ['address' => '127.0.0.1'],
                'tag' => 'api',
            ];
        }

        if (! $this->hasInboundTag($config, 'wg-in')) {
            $config['inbounds'][] = [
                'port' => 10808,
                'protocol' => 'socks',
                'settings' => [
                    'auth' => 'noauth',
                    'udp' => true,
                    'ip' => '0.0.0.0',
                ],
                'tag' => 'wg-in',
                'sniffing' => [
                    'destOverride' => ['http', 'tls', 'quic'],
                    'enabled' => true,
                ],
            ];
        }

        if (! isset($config['routing']) || ! is_array($config['routing'])) {
            $config['routing'] = [];
        }

        $config['routing']['rules'] = is_array($config['routing']['rules'] ?? null) ? array_values($config['routing']['rules']) : [];

        if (! $this->hasApiRule($config)) {
            $config['routing']['rules'][] = [
                'inboundTag' => ['api'],
                'outboundTag' => 'api',
                'type' => 'field',
            ];
        }

        $config['stats'] = new stdClass();
        $config['api'] = [
            'services' => ['StatsService'],
            'tag' => 'api',
        ];

        $levels = new stdClass();
        $levels->{'0'} = [
            'statsUserUplink' => true,
            'statsUserDownlink' => true,
        ];

        $config['policy']['levels'] = $levels;
        $config['policy']['system'] = [
            'statsInboundUplink' => true,
            'statsInboundDownlink' => true,
            'statsOutboundUplink' => true,
            'statsOutboundDownlink' => true,
        ];

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasInboundTag(array $config, string $tag): bool
    {
        foreach ($config['inbounds'] as $inbound) {
            if (is_array($inbound) && ($inbound['tag'] ?? null) === $tag) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasApiRule(array $config): bool
    {
        foreach ($config['routing']['rules'] as $rule) {
            if (is_array($rule) && ($rule['outboundTag'] ?? null) === 'api') {
                return true;
            }
        }

        return false;
    }
}
