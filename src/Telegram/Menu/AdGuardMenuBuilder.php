<?php

declare(strict_types=1);

namespace VpnBot\Telegram\Menu;

final class AdGuardMenuBuilder
{
    /**
     * @param list<string> $allowedClients
     * @param list<string> $upstreams
     * @return array{text: string, data: array<int, array<int, array<string, mixed>>>}
     */
    public function build(
        string $scheme,
        string $domain,
        string $ip,
        string $hash,
        string $password,
        ?string $adguardKey,
        bool $browserEnabled,
        string $statusLabel,
        string $safeSearchLabel,
        array $allowedClients,
        array $upstreams,
        string $thirdPartyBrowserLabel,
        string $onLabel,
        string $offLabel,
        string $changePasswordLabel,
        string $fillAllowedClientsLabel,
        string $deleteAllowedClientsLabel,
        string $checkDnsLabel,
        string $resetSettingsLabel,
        string $addUpstreamLabel,
        string $deleteLabel,
        string $backLabel,
        bool $hasSsl
    ): array {
        $text = "$scheme://$domain/adguard$hash\nLogin: admin\nPass: <span class='tg-spoiler'>$password</span>\n\n";

        if ($hasSsl) {
            $text .= "DNS over HTTPS:\n<code>$ip</code>\n<code>$scheme://$domain/dns-query$hash" . ($adguardKey !== null && $adguardKey !== '' ? "/$adguardKey" : '') . "</code>\n\n";
            $text .= "DNS over TLS:\n<code>tls://" . ($adguardKey !== null && $adguardKey !== '' ? "$adguardKey." : '') . "$domain</code>";
        }

        $text .= "\n\nstatus: $statusLabel\t\tsafesearch: $safeSearchLabel";
        $text .= $allowedClients !== [] ? "\n\nallowed clients: \n - " . implode("\n - ", $allowedClients) : '';

        $data = [
            [[
                'text' => 'web panel',
                'web_app' => ['url' => "https://$domain/adguard$hash"],
            ], [
                'text' => $thirdPartyBrowserLabel . ': ' . ($browserEnabled ? $onLabel : $offLabel),
                'callback_data' => '/adguardChBr',
            ]],
            [[
                'text' => $changePasswordLabel,
                'callback_data' => '/adguardpsswd',
            ], [
                'text' => 'ClientID' . ($adguardKey !== null && $adguardKey !== '' ? ": $adguardKey" : ''),
                'callback_data' => '/setAdguardKey',
            ]],
            [[
                'text' => $fillAllowedClientsLabel,
                'callback_data' => '/adgFillAllowedClients 0',
            ], [
                'text' => $deleteAllowedClientsLabel,
                'callback_data' => '/adgFillAllowedClients 1',
            ]],
            [[
                'text' => $checkDnsLabel,
                'callback_data' => '/checkdns',
            ], [
                'text' => $resetSettingsLabel,
                'callback_data' => '/adguardreset',
            ]],
            [[
                'text' => $addUpstreamLabel,
                'callback_data' => '/addupstream',
            ]],
        ];

        foreach ($upstreams as $index => $upstream) {
            $data[] = [[
                'text' => $upstream,
                'callback_data' => '/menu adguard',
            ], [
                'text' => $deleteLabel,
                'callback_data' => '/delupstream ' . $index,
            ]];
        }

        $data[] = [[
            'text' => $backLabel,
            'callback_data' => '/menu',
        ]];

        return [
            'text' => $text,
            'data' => $data,
        ];
    }
}
