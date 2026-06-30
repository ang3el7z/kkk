<?php

declare(strict_types=1);

namespace VpnBot\Telegram\Menu;

final class HysteriaMenuBuilder
{
    /**
     * @return array{text: string, data: array<int, array<int, array<string, string>>>}
     */
    public function build(
        string $domain,
        ?string $port,
        string $password,
        string $changePasswordLabel,
        string $backLabel
    ): array {
        return [
            'text' => implode("\n", [
                'Menu -> Hysteria',
                'server: ' . ($port !== null && $port !== '' ? "<code>$domain:$port</code>" : 'port unavailable'),
                "passwd: <code>$password</code>",
            ]),
            'data' => [
                [[
                    'text' => $changePasswordLabel,
                    'callback_data' => '/changeHysteriaPass',
                ]],
                [[
                    'text' => $backLabel,
                    'callback_data' => '/menu',
                ]],
            ],
        ];
    }
}
