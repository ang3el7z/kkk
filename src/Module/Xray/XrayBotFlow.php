<?php

declare(strict_types=1);

namespace VpnBot\Module\Xray;

final class XrayBotFlow
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function showMenu(int $page = 0): void
    {
        $config = $this->bot->getXray();
        $pac = $this->bot->getPacConf();
        $text[] = 'Menu -> ' . $this->bot->i18n('xray');

        if (! empty($config['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0])) {
            $text[] = 'fake domain: <code>' . $config['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0] . '</code>';
        }

        $text[] = 'transport: ' . ($pac['transport'] ?: 'Websocket');
        $stats = $this->bot->getXrayStats();
        $totalDownload = $this->bot->getBytes($stats['global']['download'] + $stats['session']['download']);
        $totalUpload = $this->bot->getBytes($stats['global']['upload'] + $stats['session']['upload']);
        $text[] = "↓{$totalDownload}  ↑{$totalUpload}";

        $data[] = [
            [
                'text' => $this->bot->i18n('reset stats'),
                'callback_data' => '/resetXrStats',
            ],
            [
                'text' => $this->bot->i18n('reset monthly') . ': ' . $this->bot->i18n(! empty($this->bot->getPacConf()['reset_monthly']) ? 'on' : 'off'),
                'callback_data' => '/switchMonthlyStats',
            ],
        ];
        $data[] = [[
            'text' => $this->bot->i18n('main outbound name: ') . ($pac['outbound'] ?? 'proxy'),
            'callback_data' => '/mainOutbound',
        ]];
        $data[] = [[
            'text' => $pac['linkdomain'] ?? $this->bot->i18n('cdn'),
            'callback_data' => '/addLinkDomain',
        ]];
        $data[] = [
            [
                'text' => $this->bot->i18n('RLTY') . ' ' . ($pac['transport'] === 'Reality' ? $this->bot->i18n('on') : $this->bot->i18n('off')),
                'callback_data' => '/changeTransport Reality',
            ],
            [
                'text' => $this->bot->i18n('WS') . ' ' . ($pac['transport'] === 'Websocket' ? $this->bot->i18n('on') : $this->bot->i18n('off')),
                'callback_data' => '/changeTransport Websocket',
            ],
            [
                'text' => $this->bot->i18n('XHTTP') . ($pac['transport'] === 'xhttp' ? $this->bot->i18n('on') : $this->bot->i18n('off')),
                'callback_data' => '/changeTransport xhttp',
            ],
        ];

        $ipCount = $pac['ip_count'] ?: 1;
        $hwidEnabled = ! empty($pac['hwid_limit_enabled']);
        $defaultHwids = max(1, (int) ($pac['hwid_device_count'] ?: 1));
        $data[] = [[
            'text' => $this->bot->i18n('ip limit') . ' ' . (! empty($pac['ip_limit']) ? ": {$pac['ip_limit']} sec & {$ipCount}" : $this->bot->i18n('off')),
            'callback_data' => '/setIpLimit',
        ]];
        $data[] = [
            [
                'text' => $this->bot->i18n('hwid limit') . ': ' . $this->bot->i18n($hwidEnabled ? 'on' : 'off') . " ({$defaultHwids})",
                'callback_data' => '/toggleHwidLimit xray',
            ],
            [
                'text' => $this->bot->i18n('set hwid devices count'),
                'callback_data' => '/setHwidDevices xray',
            ],
        ];

        if ($pac['transport'] === 'Reality') {
            $data[] = [
                [
                    'text' => $this->bot->i18n('changeFakeDomain'),
                    'callback_data' => '/changeFakeDomain',
                ],
                [
                    'text' => $this->bot->i18n('selfFakeDomain'),
                    'callback_data' => '/selfFakeDomain',
                ],
            ];
        }

        $data[] = [
            [
                'text' => $this->bot->i18n('v2ray templates'),
                'callback_data' => '/templates v2ray',
            ],
            [
                'text' => $this->bot->i18n('sing-box templates'),
                'callback_data' => '/templates sing',
            ],
            [
                'text' => $this->bot->i18n('mihomo templates'),
                'callback_data' => '/templates clash',
            ],
        ];
        $data[] = [
            [
                'text' => $this->bot->i18n('routes'),
                'callback_data' => '/routes',
            ],
            [
                'text' => $this->bot->i18n('tun lists'),
                'callback_data' => '/tun',
            ],
        ];

        $enabled = 0;
        $disabled = 0;
        foreach ($config['inbounds'][0]['settings']['clients'] as $client) {
            if (! empty($client['off'])) {
                $disabled++;
            } else {
                $enabled++;
            }
        }

        $showDisabled = ! empty($this->bot->getPacConf()['xtlslist']);
        $clients = array_filter(
            $config['inbounds'][0]['settings']['clients'],
            static fn (array $client): bool => ! $showDisabled ? empty($client['off']) : ! empty($client['off'])
        );
        uasort($clients, static fn (array $left, array $right): int => ($left['time'] ?: PHP_INT_MAX) <=> ($right['time'] ?: PHP_INT_MAX));

        $wireGuardClients = $this->bot->readAllWireGuardClients();
        $wgClients = [];

        foreach ($wireGuardClients['wg'] as $client) {
            $wgClients[$client['interface']['PrivateKey']] = [
                'container' => '1',
                'name' => $client['interface']['## name'],
            ];
        }

        foreach ($wireGuardClients['wg1'] as $client) {
            $wgClients[$client['interface']['PrivateKey']] = [
                'container' => '2',
                'name' => $client['interface']['## name'],
            ];
        }

        $pages = (int) ceil(count($clients) / $this->bot->limit);
        $page = min($page, $pages - 1);
        $page = $page === -2 ? $pages - 1 : $page;
        $clients = $page !== -1 ? array_slice($clients, $page * $this->bot->limit, $this->bot->limit, true) : $clients;

        foreach ($clients as $index => $client) {
            $download = $this->bot->getBytes($stats['users'][$index]['global']['download'] + $stats['users'][$index]['session']['download']);
            $upload = $this->bot->getBytes($stats['users'][$index]['global']['upload'] + $stats['users'][$index]['session']['upload']);
            $time = ! empty($client['time']) ? $this->bot->getTime($client['time']) : '';
            $wgSuffix = ! empty($client['awg']) ? ' ' . $wgClients[$client['awg']]['container'] . '-' . $wgClients[$client['awg']]['name'] : '';

            $data[] = [[
                'text' => $client['email'] . ($time ? ": {$time}" : '') . " (↓{$download}  ↑{$upload}){$wgSuffix}",
                'callback_data' => "/userXr {$index}",
            ]];
        }

        if ($page !== -1 && $pages > 1) {
            $data[] = [
                [
                    'text' => '<<',
                    'callback_data' => '/xray ' . ($page - 1 >= 0 ? $page - 1 : $pages - 1),
                ],
                [
                    'text' => $page + 1,
                    'callback_data' => "/xray {$page}",
                ],
                [
                    'text' => '>>',
                    'callback_data' => '/xray ' . ($page < $pages - 1 ? $page + 1 : 0),
                ],
            ];
        }

        $data[] = [
            [
                'text' => $this->bot->i18n('add'),
                'callback_data' => '/addXrUser',
            ],
            [
                'text' => $this->bot->i18n('on') . " {$enabled} " . (! $showDisabled ? '✅' : ''),
                'callback_data' => '/listXr 0',
            ],
            [
                'text' => $this->bot->i18n('off') . " {$disabled} " . ($showDisabled ? '✅' : ''),
                'callback_data' => '/listXr 1',
            ],
        ];
        $data[] = [[
            'text' => $this->bot->i18n('back'),
            'callback_data' => '/menu',
        ]];

        $this->bot->update(
            $this->bot->input['chat'],
            $this->bot->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function chooseTemplate(string $arg): void
    {
        $parts = explode('_', $arg);
        $config = $this->bot->buildSubscriptionModule()->updateClientTemplate(
            $this->bot->getXray(),
            (string) $parts[0],
            (int) $parts[1],
            ! empty($parts[2]) ? (string) $parts[2] : null
        );
        $this->bot->restartXray($config, true);
        $this->showUser((int) $parts[1]);
    }

    public function showTemplateUser(string $type, int $index): void
    {
        $config = $this->bot->getXray();
        $text[] = 'Menu -> ' . $this->bot->i18n('xray') . ' -> ' . $config['inbounds'][0]['settings']['clients'][$index]['email'] . "\n";
        $templates = $this->bot->buildPacTemplateStore()->allTemplates($type);
        $data[] = [[
            'text' => 'default',
            'callback_data' => "/choiceTemplate {$type}_{$index}",
        ]];
        $data[] = [[
            'text' => 'origin',
            'callback_data' => '/choiceTemplate ' . $type . '_' . $index . '_' . base64_encode('origin'),
        ]];

        foreach ($templates as $name => $_template) {
            $data[] = [[
                'text' => $name,
                'callback_data' => '/choiceTemplate ' . $type . '_' . $index . '_' . base64_encode($name),
            ]];
        }

        $data[] = [[
            'text' => $this->bot->i18n('back'),
            'callback_data' => "/userXr {$index}",
        ]];

        $this->bot->update(
            $this->bot->input['chat'],
            $this->bot->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function showUser(int $index): void
    {
        $xray = $this->bot->getXray();
        $client = $xray['inbounds'][0]['settings']['clients'][$index];
        $pac = $this->bot->getPacConf();
        $domain = $this->bot->getDomain($pac['transport'] != 'Reality');
        $scheme = empty($this->bot->nginxGetTypeCert()) ? 'http' : 'https';
        $hash = $this->bot->getHashBot();

        $devices = $this->bot->getHwidDevicesByUser($client['id']);
        $hwidEnabled = ! empty($pac['hwid_limit_enabled']) && empty($client['hwid_disabled']);
        $defaultHwid = max(1, (int) ($pac['hwid_device_count'] ?: 1));
        $hwidLimit = $client['hwid_limit'] ? (int) $client['hwid_limit'] : $defaultHwid;

        $text[] = 'Menu -> ' . $this->bot->i18n('xray') . ' -> ' . $client['email'] . "\n";
        if (file_exists(__DIR__ . '/../../../app/subscription.php')) {
            $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}/sub?id={$client['id']}'>subscription</a>";
        }
        $text[] = '<pre><code>' . $this->bot->linkXray($index) . "</code></pre>\n";

        $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}?t=s&r=v&s={$client['id']}#{$client['email']}'>import://v2rayng</a>";
        $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}?t=si&r=si&s={$client['id']}#{$client['email']}'>import://sing-box</a>";
        $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}?t=s&r=st&s={$client['id']}#{$client['email']}'>import://streisand</a>";
        $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}?t=si&r=h&s={$client['id']}#{$client['email']}'>import://hiddify</a>";
        $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}?t=si&r=k&s={$client['id']}#{$client['email']}'>import://karing</a>";
        $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}?t=cl&r=c&s={$client['id']}#{$client['email']}'>import://mihomo</a>";
        $text[] = "<a href='{$scheme}://{$domain}/pac{$hash}?t=cl&r=rh&s={$client['id']}#{$client['email']}'>import://rabbit-hole</a>";

        $singboxConfig = $this->buildSerializedConfigUrl($scheme, $domain, $hash, 'si', $client['id']);
        $xrayConfig = $this->buildSerializedConfigUrl($scheme, $domain, $hash, 's', $client['id']);
        $mihomoConfig = $this->buildSerializedConfigUrl($scheme, $domain, $hash, 'cl', $client['id']);

        $text[] = "\nxray config: <pre><code>{$xrayConfig}</code></pre>";
        $text[] = "sing-box config: <pre><code>{$singboxConfig}</code></pre>";
        $text[] = "mihomo config: <pre><code>{$mihomoConfig}</code></pre>";
        $text[] = "sing-box windows: <a href='{$scheme}://{$domain}/pac{$hash}?t=si&r=w&s={$client['id']}'>windows service</a>";

        $stats = $this->bot->getXrayStats();
        $download = $this->bot->getBytes($stats['users'][$index]['global']['download'] + $stats['users'][$index]['session']['download']);
        $upload = $this->bot->getBytes($stats['users'][$index]['global']['upload'] + $stats['users'][$index]['session']['upload']);

        $data[] = [[
            'text' => $this->bot->i18n('reset stats') . ": ↓{$download}  ↑{$upload}",
            'callback_data' => "/resetXrUser {$index}",
        ]];
        $data[] = [
            [
                'text' => $this->bot->i18n('v2ray'),
                'web_app' => ['url' => "https://{$domain}/pac{$hash}?t=s&s={$client['id']}"],
            ],
            [
                'text' => $this->bot->i18n('singbox'),
                'web_app' => ['url' => "https://{$domain}/pac{$hash}?t=si&s={$client['id']}"],
            ],
            [
                'text' => $this->bot->i18n('mihomo'),
                'web_app' => ['url' => "https://{$domain}/pac{$hash}?t=cl&s={$client['id']}"],
            ],
        ];
        $data[] = [
            [
                'text' => $this->bot->i18n('v2ray ⬇️'),
                'callback_data' => "/dw {$index} s",
            ],
            [
                'text' => $this->bot->i18n('singbox ⬇️'),
                'callback_data' => "/dw {$index} si",
            ],
            [
                'text' => $this->bot->i18n('mihomo ⬇️'),
                'callback_data' => "/dw {$index} cl",
            ],
        ];
        $data[] = [
            [
                'text' => $client['time'] ? 'timer: ' . $this->bot->getTime($client['time']) : $this->bot->i18n('timer'),
                'callback_data' => "/timerXr {$index}",
            ],
            [
                'text' => $this->bot->i18n($client['off'] ? 'off' : 'on'),
                'callback_data' => "/switchXr {$index}",
            ],
        ];
        $data[] = [
            [
                'text' => $this->bot->i18n('v2ray') . ': ' . $this->resolveTemplateLabel($client['v2raytemplate'] ?? null, $pac['defaultv2raytemplate'] ?? null, $pac['v2raytemplates'] ?? []),
                'callback_data' => "/templateUser v2ray {$index}",
            ],
            [
                'text' => $this->bot->i18n('singbox') . ': ' . $this->resolveTemplateLabel($client['singtemplate'] ?? null, $pac['defaultsingtemplate'] ?? null, $pac['singtemplates'] ?? []),
                'callback_data' => "/templateUser sing {$index}",
            ],
            [
                'text' => $this->bot->i18n('mihomo') . ': ' . $this->resolveTemplateLabel($client['clashtemplate'] ?? null, $pac['defaultclashtemplate'] ?? null, $pac['clashtemplates'] ?? []),
                'callback_data' => "/templateUser clash {$index}",
            ],
        ];
        $data[] = [
            [
                'text' => $this->bot->i18n('qr short'),
                'callback_data' => "/qrXray {$index}",
            ],
            [
                'text' => $this->bot->i18n('qr v2ray'),
                'callback_data' => "/qrXray {$index}_1",
            ],
            [
                'text' => $this->bot->i18n('qr singbox'),
                'callback_data' => "/qrXray {$index}_2",
            ],
        ];
        $data[] = [[
            'text' => $this->bot->i18n('hwid limit') . ': ' . ($hwidEnabled ? $hwidLimit : $this->bot->i18n('off')) . ' (' . count($devices) . ')',
            'callback_data' => "/hwidUser {$index}",
        ]];
        $data[] = [
            [
                'text' => $this->bot->i18n('rename'),
                'callback_data' => "/renameXrUser {$index}",
            ],
            [
                'text' => $this->bot->i18n('delete'),
                'callback_data' => "/delxr {$index}",
            ],
        ];
        $data[] = [[
            'text' => $this->bot->i18n('back'),
            'callback_data' => '/xray',
        ]];

        $this->bot->update(
            $this->bot->input['chat'],
            $this->bot->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function deleteUser(int $index): void
    {
        $config = $this->bot->getXray();
        $stats = $this->bot->getXrayStats();

        foreach ($config['inbounds'][0]['settings']['clients'] as $clientIndex => $client) {
            if ($index !== $clientIndex) {
                continue;
            }

            $this->bot->deleteHwidUser($config['inbounds'][0]['settings']['clients'][$clientIndex]['id']);
            unset($config['inbounds'][0]['settings']['clients'][$clientIndex], $stats['users'][$clientIndex]);
            $this->bot->setXrayStats($stats);
            $this->bot->restartXray($config);
            $this->bot->adguardXrayClients();
            break;
        }

        $this->showMenu();
    }

    public function addUsers(string $users): void
    {
        $config = $this->bot->getXray();
        $pac = $this->bot->getPacConf();
        $parsedUsers = array_map(static fn (string $item): string => trim($item), explode(',', $users));
        $parsedUsers = array_map(static fn (string $item): array => explode(':', $item), $parsedUsers);
        $uuids = [];
        $emails = [];

        foreach ($config['inbounds'][0]['settings']['clients'] as $client) {
            $uuids[] = $client['id'];
            $emails[] = $client['email'];
        }

        foreach ($parsedUsers as $user) {
            $uuid = $user[1] ?: trim($this->bot->ssh('xray uuid', 'xr'));
            if (in_array($uuid, $uuids, true) || in_array($user[0], $emails, true)) {
                $this->bot->send($this->bot->input['chat'], "user {$user[0]} already exists");
                $this->showMenu();

                return;
            }

            $config['inbounds'][0]['settings']['clients'][] = $pac['transport'] !== 'Reality'
                ? [
                    'id' => $uuid,
                    'email' => $user[0],
                ]
                : [
                    'id' => $uuid,
                    'flow' => 'xtls-rprx-vision',
                    'email' => $user[0],
                ];
        }

        $this->bot->restartXray($config);
        $this->bot->adguardXrayClients();

        if (count($parsedUsers) === 1) {
            $this->showUser(count($config['inbounds'][0]['settings']['clients']) - 1);

            return;
        }

        $this->showMenu();
    }

    public function switchUser(int $index, int $skipMenu = 0, bool $preserveTimer = false): void
    {
        $config = $this->bot->getXray();

        if (! $preserveTimer) {
            unset($config['inbounds'][0]['settings']['clients'][$index]['time']);
        }

        if (empty($config['inbounds'][0]['settings']['clients'][$index]['off'])) {
            $config['inbounds'][0]['settings']['clients'][$index]['off'] = $config['inbounds'][0]['settings']['clients'][$index]['id'];
            $config['inbounds'][0]['settings']['clients'][$index]['id'] = trim($this->bot->ssh('xray uuid', 'xr'));
        } else {
            $config['inbounds'][0]['settings']['clients'][$index]['id'] = $config['inbounds'][0]['settings']['clients'][$index]['off'];
            unset($config['inbounds'][0]['settings']['clients'][$index]['off']);
        }

        $this->bot->restartXray($config);

        if ($skipMenu === 0) {
            $this->showUser($index);
        }
    }

    public function renameUser(string $name, int $index): void
    {
        $config = $this->bot->getXray();
        $config['inbounds'][0]['settings']['clients'][$index]['email'] = $name;
        $this->bot->restartXray($config);
        $this->bot->adguardXrayClients();
        $this->showUser($index);
    }

    private function buildSerializedConfigUrl(string $scheme, string $domain, string $hash, string $type, string $clientId): string
    {
        return $scheme . '://' . $domain . '/pac' . $hash . '/' . base64_encode(serialize([
            'h' => $hash,
            't' => $type,
            's' => $clientId,
        ]));
    }

    /**
     * @param array<string, mixed> $templates
     */
    private function resolveTemplateLabel(?string $clientTemplate, ?string $defaultTemplate, array $templates): string
    {
        if ($clientTemplate) {
            return base64_decode($clientTemplate);
        }

        $defaultName = $defaultTemplate ? base64_decode($defaultTemplate) : null;

        return 'default(' . ($defaultName && ! empty($templates[$defaultName]) ? $defaultName : 'origin') . ')';
    }
}
