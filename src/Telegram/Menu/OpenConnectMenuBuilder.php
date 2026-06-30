<?php

declare(strict_types=1);

namespace VpnBot\Telegram\Menu;

final class OpenConnectMenuBuilder
{
    /**
     * @param list<string> $clients
     * @return array{text: string, data: array<int, array<int, array<string, string>>>}
     */
    public function build(
        string $domain,
        string $subdomain,
        string $password,
        string $dns,
        bool $exposeIroutes,
        array $clients,
        string $changeSubdomainLabel,
        string $changeSecretLabel,
        string $changePasswordLabel,
        string $dnsLabel,
        string $listSubnetLabel,
        string $exposeIroutesLabel,
        string $onLabel,
        string $offLabel,
        string $addPeerLabel,
        string $deleteLabel,
        string $backLabel,
        ?string $camouflageSecret = null
    ): array {
        $text = ['Menu -> OpenConnect'];

        if ($camouflageSecret !== null && $camouflageSecret !== '') {
            $text[] = "<code>https://$subdomain.$domain/?$camouflageSecret</code>";
        }

        $text[] = "password: <span class='tg-spoiler'>$password</span>";
        $data = [
            [[
                'text' => $changeSubdomainLabel,
                'callback_data' => '/changeOcDomain',
            ]],
            [[
                'text' => $changeSecretLabel,
                'callback_data' => '/changeCamouflage',
            ], [
                'text' => $changePasswordLabel,
                'callback_data' => '/changeOcPass',
            ]],
            [[
                'text' => $dnsLabel . ': ' . $dns,
                'callback_data' => '/changeOcDns',
            ]],
            [[
                'text' => $listSubnetLabel,
                'callback_data' => '/subnet 0_0_1',
            ]],
            [[
                'text' => $exposeIroutesLabel . ' ' . ($exposeIroutes ? $onLabel : $offLabel),
                'callback_data' => '/changeOcExpose',
            ]],
            [[
                'text' => $addPeerLabel,
                'callback_data' => '/addOcUser',
            ]],
        ];

        foreach ($clients as $index => $client) {
            $data[] = [[
                'text' => $deleteLabel . ' ' . $client,
                'callback_data' => '/deloc ' . $index,
            ]];
        }

        $data[] = [[
            'text' => $backLabel,
            'callback_data' => '/menu',
        ]];

        return [
            'text' => implode("\n", $text),
            'data' => $data,
        ];
    }
}
