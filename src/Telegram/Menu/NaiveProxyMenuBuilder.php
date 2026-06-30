<?php

declare(strict_types=1);

namespace VpnBot\Telegram\Menu;

final class NaiveProxyMenuBuilder
{
    /**
     * @param array{user: string, password: string} $credentials
     * @return array{text: string, data: array<int, array<int, array<string, string>>>}
     */
    public function build(
        string $domain,
        string $subdomain,
        array $credentials,
        string $changeSubdomainLabel,
        string $changeLoginLabel,
        string $changePasswordLabel,
        string $backLabel
    ): array {
        return [
            'text' => implode("\n", [
                'Menu -> NaiveProxy',
                sprintf(
                    '<code>https://%s:%s@%s.%s</code>',
                    $credentials['user'],
                    $credentials['password'],
                    $subdomain,
                    $domain,
                ),
            ]),
            'data' => [
                [[
                    'text' => $changeSubdomainLabel,
                    'callback_data' => '/changeNaiveSubdomain',
                ]],
                [[
                    'text' => $changeLoginLabel,
                    'callback_data' => '/changeNaiveUser',
                ], [
                    'text' => $changePasswordLabel,
                    'callback_data' => '/changeNaivePass',
                ]],
                [[
                    'text' => $backLabel,
                    'callback_data' => '/menu',
                ]],
            ],
        ];
    }
}
