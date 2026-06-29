<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

final class WireGuardConfigCodec
{
    /**
     * @return array{interface: array<string, string>, peers: array<int, array<string, string>>}
     */
    public function parseConfig(string $config): array
    {
        $lines = preg_split('~\R~', $config) ?: [];
        $blocks = [];
        $index = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('~^\[(.+)\]$~', $line, $matches) === 1) {
                $index++;
                $blocks[$index]['type'] = $matches[1] === 'Interface' ? 'interface' : 'peer';

                continue;
            }

            if ($index === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $blocks[$index][trim($parts[0])] = trim($parts[1]);
        }

        return $this->normalizeBlocks($blocks);
    }

    /**
     * @return array{interface: array<string, string>, peers: array<int, array<string, string>>}
     */
    public function parseStatus(string $status): array
    {
        $lines = preg_split('~\R~', $status) ?: [];
        $blocks = [];
        $index = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('~^(interface|peer):~', $line, $matches) === 1) {
                $index++;
                $blocks[$index]['type'] = $matches[1] === 'interface' ? 'interface' : 'peer';
            }

            if ($index === 0) {
                continue;
            }

            $parts = explode(':', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $blocks[$index][trim($parts[0])] = trim($parts[1]);
        }

        return $this->normalizeBlocks($blocks);
    }

    /**
     * @param array{interface: array<string, string>, peers?: array<int, array<string, string>>} $data
     */
    public function renderConfig(array $data): string
    {
        $lines = ['[Interface]'];

        foreach ($data['interface'] as $key => $value) {
            $lines[] = sprintf('%s = %s', $key, $value);
        }

        foreach ($data['peers'] ?? [] as $peer) {
            $lines[] = '';
            $lines[] = ! empty($peer['# PublicKey']) ? '# [Peer]' : '[Peer]';

            foreach ($peer as $key => $value) {
                $lines[] = sprintf('%s = %s', $key, $value);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, string> $peer
     */
    public function resolveClientName(array $peer): string
    {
        foreach ($peer as $key => $value) {
            if (preg_match('~^#.*name$~', $key) === 1) {
                return $value;
            }
        }

        return $peer['AllowedIPs'] ?? $peer['Address'] ?? '';
    }

    /**
     * @param array<int, array<string, string>> $blocks
     * @return array{interface: array<string, string>, peers: array<int, array<string, string>>}
     */
    private function normalizeBlocks(array $blocks): array
    {
        $result = [
            'interface' => [],
            'peers' => [],
        ];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            unset($block['type']);

            if ($type === 'interface') {
                $result['interface'] = $block;

                continue;
            }

            if ($type === 'peer') {
                $result['peers'][] = $block;
            }
        }

        return $result;
    }
}
