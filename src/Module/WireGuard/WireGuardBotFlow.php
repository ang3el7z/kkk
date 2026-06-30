<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

final class WireGuardBotFlow
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function title(): string
    {
        $config = $this->bot->getPacConf();

        return $this->bot->i18n($config[$this->bot->getInstanceWG(1) . 'amnezia'] ? 'amnezia' : 'wg_title') . ' ' . $config['wg_instance'];
    }

    /**
     * @return array{text: string, data: array<int, mixed>}
     */
    public function statusMenu(int $page = 0): array
    {
        $config = $this->bot->getPacConf();
        $serverConfig = $this->bot->readConfig();
        $status = $this->bot->readStatus();

        if (empty($status)) {
            return [
                'text' => "Menu -> " . $this->title() . "\n\nerror status",
                'data' => [[
                    [
                        'text' => $this->bot->i18n('back'),
                        'callback_data' => '/menu',
                    ],
                ]],
            ];
        }

        $clientRows = $this->listClients($page);
        $prefix = $this->bot->getInstanceWG(1);
        $blockTorrent = $config[$prefix . 'blocktorrent'] ?? false;
        $exchange = $config[$prefix . 'exchange'] ?? false;
        $dns = $config[$prefix . 'dns'] ?? false;
        $mtu = $config[$prefix . 'mtu'] ?? $this->bot->mtu;
        $amnezia = $config[$prefix . 'amnezia'] ?? false;
        $endpoint = $config[$prefix . 'endpoint'] ?? false;

        if (! empty($amnezia)) {
            $amneziaKeys = $this->bot->amneziaKeys();
            $text[] = '<code>' . implode(
                "\n",
                array_map(
                    static fn ($key, $value): string => htmlspecialchars($key) . ': ' . htmlspecialchars($value),
                    array_keys($amneziaKeys),
                    $amneziaKeys
                )
            ) . "</code>\n";
        }

        $data = [
            [
                [
                    'text' => $this->bot->i18n(! $blockTorrent ? 'on' : 'off') . ' ' . $this->bot->i18n('torrent') . ' ',
                    'callback_data' => "/switchTorrent {$page}",
                ],
                [
                    'text' => $this->bot->i18n(! $exchange ? 'on' : 'off') . ' ' . $this->bot->i18n('exchange') . ' ',
                    'callback_data' => "/switchExchange {$page}",
                ],
                [
                    'text' => $this->bot->i18n('listSubnet'),
                    'callback_data' => "/subnet {$page}",
                ],
            ],
            [
                [
                    'text' => $this->bot->i18n('defaultDNS') . ': ' . ($dns ?: $this->bot->dns),
                    'callback_data' => "/defaultDNS {$page}",
                ],
                [
                    'text' => $this->bot->i18n('defaultMTU') . ': ' . $mtu,
                    'callback_data' => "/defaultMTU {$page}",
                ],
            ],
            [[
                'text' => $this->bot->i18n('endpoint') . ': ' . ($endpoint ? $this->bot->ip : $this->bot->getDomain()),
                'callback_data' => "/switchEndpoint {$page}",
            ]],
            [[
                'text' => $this->bot->i18n('add peer'),
                'callback_data' => "/menu addpeer {$page}",
            ]],
        ];

        if (! empty($amnezia)) {
            array_unshift($data, [
                [
                    'text' => 'reset obf-keys',
                    'callback_data' => "/resetAmnezia {$page}",
                ],
                [
                    'text' => 'reduce to 1',
                    'callback_data' => "/reduceAmnezia {$page}",
                ],
            ]);
        }

        array_unshift($data, [[
            'text' => $this->bot->i18n($amnezia ? 'on' : 'off') . ' amnezia',
            'callback_data' => "/switchAmnezia {$page}",
        ]]);

        if ($clientRows) {
            $data = array_merge($data, $clientRows);
        }

        if (! empty($serverConfig['peers'])) {
            $pages = (int) ceil(count($serverConfig['peers']) / $this->bot->limit);
            $page = min($page, $pages - 1);
            $page = $page === -2 ? $pages - 1 : $page;
            $serverConfig['peers'] = array_slice($serverConfig['peers'], $page * $this->bot->limit, $this->bot->limit, true);

            foreach ($serverConfig['peers'] as $index => $peer) {
                if (! empty($peer['# PublicKey'])) {
                    $serverConfig['peers'][$index]['online'] = 'off';
                    continue;
                }

                $serverConfig['peers'][$index]['status'] = $status ? $this->bot->getStatusPeer($peer['PublicKey'], $status['peers']) : 'error';
                $serverConfig['peers'][$index]['online'] = preg_match('~^(\d+ seconds|[12] minute)~', $serverConfig['peers'][$index]['status']['latest handshake']) ? 'online' : '';
            }

            foreach ($serverConfig['peers'] as $peer) {
                if (empty($peer['# PublicKey'])) {
                    preg_match_all('~([0-9.]+\.?)\s(\w+)~', $peer['status']['transfer'], $matches);
                    $traffic = $matches[0] ? ceil($matches[1][1]) . '↓' . substr($matches[2][1], 0, 1) . '/' . ceil($matches[1][0]) . '↑' . substr($matches[2][0], 0, 1) : '';
                } else {
                    $traffic = '';
                }

                $row = [
                    'name' => $this->bot->getName($peer),
                    'time' => $this->bot->getTime(strtotime($peer['## time'])),
                    'status' => $peer['online'] === 'off' ? '🚷' : $this->bot->i18n($peer['online'] ? 'on' : 'off'),
                    'traffic' => $traffic,
                ];
                $padding = [
                    'name' => max(mb_strlen($row['name']), $padding['name'] ?? 0),
                    'time' => max($row['time'] === '♾' ? 4 : mb_strlen($row['time']), $padding['time'] ?? 0),
                    'status' => max(mb_strlen($row['status']), $padding['status'] ?? 0),
                    'traffic' => max(mb_strlen($row['traffic']), $padding['traffic'] ?? 0),
                ];
                $peers[] = $row;
            }

            foreach ($peers ?? [] as $peer) {
                $text[] = implode('', [
                    $this->bot->pad($peer['name'], $padding['name'] - mb_strlen($peer['name'])),
                    $this->bot->pad(' ' . $peer['time'], $padding['time'] - mb_strlen($peer['time'])),
                    $this->bot->pad($peer['status'], $padding['status'] - mb_strlen($peer['status'])),
                    $this->bot->pad(' ' . $peer['traffic'], $padding['traffic'] - mb_strlen($peer['traffic'])),
                ]);
            }
        }

        $text = "Menu -> " . $this->title() . "\n\n<code>" . implode(PHP_EOL, $text ?: []) . '</code>';
        $data[] = [
            [
                'text' => $this->bot->i18n('update status'),
                'callback_data' => "/menu wg {$page}",
            ],
            [
                'text' => $this->bot->i18n('back'),
                'callback_data' => '/menu',
            ],
        ];

        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function promptDefaultDns(int $page = 0): void
    {
        $reply = $this->bot->send(
            $this->bot->input['chat'],
            "@{$this->bot->input['username']} enter dns separated by commas",
            $this->bot->input['message_id'],
            reply: 'enter dns separated by commas',
        );
        $_SESSION['reply'][$reply['result']['message_id']] = [
            'start_message' => $this->bot->input['message_id'],
            'start_callback' => $this->bot->input['callback_id'],
            'callback' => 'setDNS',
            'args' => [$page],
        ];
    }

    public function promptDefaultMtu(int $page = 0): void
    {
        $reply = $this->bot->send(
            $this->bot->input['chat'],
            "@{$this->bot->input['username']} enter MTU",
            $this->bot->input['message_id'],
            reply: 'enter MTU',
        );
        $_SESSION['reply'][$reply['result']['message_id']] = [
            'start_message' => $this->bot->input['message_id'],
            'start_callback' => $this->bot->input['callback_id'],
            'callback' => 'setMTU',
            'args' => [$page],
        ];
    }

    public function promptClientMtu(int $client, int $page = 0): void
    {
        $reply = $this->bot->send(
            $this->bot->input['chat'],
            "@{$this->bot->input['username']} enter MTU",
            $this->bot->input['message_id'],
            reply: 'enter MTU',
        );
        $_SESSION['reply'][$reply['result']['message_id']] = [
            'start_message' => $this->bot->input['message_id'],
            'start_callback' => $this->bot->input['callback_id'],
            'callback' => 'changeClientMTU',
            'args' => [$client, $page],
        ];
    }

    public function saveDefaultDns(string $text, int $page = 0): void
    {
        $config = $this->bot->getPacConf();
        $key = $this->bot->getInstanceWG(1) . 'dns';

        if ($text !== '') {
            $config[$key] = $text;
        } else {
            unset($config[$key]);
        }

        $this->bot->setPacConf($config);
        $this->bot->menu('wg', $page);
    }

    public function saveDefaultMtu(string $text, int $page = 0): void
    {
        $config = $this->bot->getPacConf();
        $key = $this->bot->getInstanceWG(1) . 'mtu';

        if ($text !== '') {
            $config[$key] = $text;
        } else {
            unset($config[$key]);
        }

        $this->bot->setPacConf($config);
        $this->bot->menu('wg', $page);
    }

    public function saveClientMtu(string $text, int $client, int $page = 0): void
    {
        $clients = $this->bot->readClients();

        if (! empty((int) $text)) {
            $clients[$client]['interface']['MTU'] = $text;
        } else {
            unset($clients[$client]['interface']['MTU']);
        }

        $this->bot->saveClients($clients);
        $this->bot->menu('client', "{$client}_{$page}");
    }

    public function promptSubnetAdd(int $wgPage, int $page, int $openConnect): void
    {
        $reply = $this->bot->send(
            $this->bot->input['chat'],
            "@{$this->bot->input['username']} enter subnet separated by commas",
            $this->bot->input['message_id'],
            reply: 'enter subnet separated by commas',
        );
        $_SESSION['reply'][$reply['result']['message_id']] = [
            'start_message' => $this->bot->input['message_id'],
            'start_callback' => $this->bot->input['callback_id'],
            'callback' => 'subnetSave',
            'args' => [$wgPage, $page, $openConnect],
        ];
    }

    public function saveSubnet(string $text, int $wgPage, int $page, int $openConnect): void
    {
        $config = $this->bot->getPacConf();
        $subnets = explode(',', $text);

        if ($subnets) {
            $config['subnets'] = array_merge($config['subnets'] ?: [], array_filter(array_map(static fn (string $item): string => trim($item), $subnets)));
            $this->bot->setPacConf($config);
            $page = floor(count($config['subnets']) / $this->bot->limit);
        }

        if (! empty($openConnect)) {
            $this->bot->ocservRoute();
        }

        $this->showSubnet($wgPage, $page, $openConnect);
    }

    public function deleteSubnet(int $wgPage, int $key, int $page = 0, int $openConnect = 0): void
    {
        $config = $this->bot->getPacConf();
        unset($config['subnets'][$key]);
        $this->bot->setPacConf($config);

        if (! empty($openConnect)) {
            $this->bot->ocservRoute();
        }

        $this->showSubnet($wgPage, $page, $openConnect);
    }

    public function showSubnet(int $wgPage = 0, int $page = 0, int $openConnect = 0): void
    {
        $count = $this->bot->limit;
        $text = 'Menu -> ' . ($openConnect ? 'Openconnect' : 'Wireguard') . ' -> ' . $this->bot->i18n('listSubnet') . "\n";
        $data[] = [[
            'text' => $this->bot->i18n('calc'),
            'callback_data' => '/calc',
        ]];
        $data[] = [[
            'text' => $this->bot->i18n('add'),
            'callback_data' => "/subnetAdd {$wgPage}_{$page}_{$openConnect}",
        ]];

        $subnets = $this->bot->getPacConf()['subnets'];
        if (! empty($subnets)) {
            $pages = (int) ceil(count($subnets) / $count);
            $page = min($page, $pages - 1);
            $page = $page === -2 ? $pages - 1 : $page;
            $subnets = $page !== -1 ? array_slice($subnets, $page * $count, $count, true) : $subnets;

            foreach ($subnets as $key => $subnet) {
                $data[] = [[
                    'text' => $this->bot->i18n('delete') . " {$subnet}",
                    'callback_data' => "/subnetDelete {$wgPage}_{$key}_{$page}_{$openConnect}",
                ]];
            }

            if ($page !== -1 && $pages > 1) {
                $suffix = $openConnect ? '_1' : '';
                $data[] = [
                    [
                        'text' => '<<',
                        'callback_data' => '/subnet ' . $wgPage . '_' . ($page - 1 >= 0 ? $page - 1 : $pages - 1) . $suffix,
                    ],
                    [
                        'text' => $page + 1,
                        'callback_data' => "/subnet {$wgPage}_{$page}{$suffix}",
                    ],
                    [
                        'text' => '>>',
                        'callback_data' => '/subnet ' . $wgPage . '_' . ($page < $pages - 1 ? $page + 1 : 0) . $suffix,
                    ],
                ];
            }
        }

        $data[] = [[
            'text' => $this->bot->i18n('back'),
            'callback_data' => $openConnect ? '/menu oc' : "/menu wg {$wgPage}",
        ]];

        $this->bot->update(
            $this->bot->input['chat'],
            $this->bot->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function showAllowedIpsChooser(int $client, int $page = 0): void
    {
        $clients = $this->bot->readClients();
        $name = $this->bot->getName($clients[$client]['interface']);
        $text = "Menu -> Wireguard -> {$name} -> Change AllowedIPs\n\n";
        $data[] = [[
            'text' => $this->bot->i18n('all traffic'),
            'callback_data' => "/changeIps all_{$client}_{$page}",
        ]];
        $data[] = [[
            'text' => $this->bot->i18n('subnet'),
            'callback_data' => "/changeIps subnet_{$client}_{$page}",
        ]];

        if ($this->bot->getPacConf()['subnets']) {
            $data[] = [[
                'text' => $this->bot->i18n('listSubnet'),
                'callback_data' => "/changeIps list_{$client}_{$page}",
            ]];
        }

        $data[] = [[
            'text' => $this->bot->i18n('proxy ip'),
            'callback_data' => "/changeIps proxy_{$client}_{$page}",
        ]];
        $data[] = [[
            'text' => $this->bot->i18n('back'),
            'callback_data' => "/menu client {$client}_{$page}",
        ]];

        $this->bot->update(
            $this->bot->input['chat'],
            $this->bot->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function changeIps(string $type, int $client, int $page = 0): void
    {
        switch ($type) {
            case 'all':
                $this->saveIps('0.0.0.0/0', $client, $page);
                break;
            case 'subnet':
                $reply = $this->bot->send(
                    $this->bot->input['chat'],
                    "@{$this->bot->input['username']} list subnets separated by commas",
                    $this->bot->input['message_id'],
                    reply: 'list subnets separated by commas',
                );
                $_SESSION['reply'][$reply['result']['message_id']] = [
                    'start_message' => $this->bot->input['message_id'],
                    'callback' => 'setIps',
                    'args' => [$client, $page],
                ];
                break;
            case 'list':
                $this->saveIps(implode(',', $this->bot->getPacConf()['subnets']), $client, $page);
                break;
            case 'proxy':
                $this->saveIps(trim($this->bot->ssh("getent hosts proxy | awk '{ print $1 }'")) . '/32', $client, $page);
                break;
        }
    }

    public function saveIps(string $ips, int $client, int $page = 0): void
    {
        $clients = $this->bot->readClients();
        $clients[$client]['peers'][0]['AllowedIPs'] = $ips;
        $this->bot->saveClients($clients);
        $this->bot->menu('client', "{$client}_{$page}");
    }

    /**
     * @return array<int, mixed>
     */
    public function listClients(int $page): array
    {
        $count = $this->bot->limit;
        $clients = $this->bot->readClients();

        if (empty($clients)) {
            return [];
        }

        $pages = (int) ceil(count($clients) / $count);
        $page = min($page, $pages - 1);
        $page = $page === -2 ? $pages - 1 : $page;
        $clients = $page !== -1 ? array_slice($clients, $page * $count, $count, true) : $clients;
        $xray = $this->bot->getXray();

        foreach ($clients as $index => $client) {
            $vless = false;
            foreach ($xray['inbounds'][0]['settings']['clients'] as $xrayClient) {
                if (! empty($xrayClient['awg']) && $xrayClient['awg'] === $client['interface']['PrivateKey']) {
                    $vless = $xrayClient['email'];
                    break;
                }
            }

            $data[] = [
                [
                    'text' => $this->bot->getName($client['interface']),
                    'callback_data' => "/menu client {$index}_{$page}",
                ],
                [
                    'text' => $this->bot->i18n('vless') . ': ' . $this->bot->i18n($vless !== false ? 'on' : 'off') . ($vless !== false ? " {$vless}" : ''),
                    'callback_data' => "/choiceVless {$index}_{$page}_0",
                ],
            ];
        }

        if ($page !== -1 && $pages > 1) {
            $data[] = [
                [
                    'text' => '<<',
                    'callback_data' => '/menu wg ' . ($page - 1 >= 0 ? $page - 1 : $pages - 1),
                ],
                [
                    'text' => $page + 1,
                    'callback_data' => "/menu wg {$page}",
                ],
                [
                    'text' => '>>',
                    'callback_data' => '/menu wg ' . ($page < $pages - 1 ? $page + 1 : 0),
                ],
            ];
        }

        return $data ?? [];
    }

    /**
     * @return array{text: string, data: array<int, mixed>|false}
     */
    public function clientMenu(int $client, int $page): array
    {
        $clients = $this->bot->readClients();

        if (! $clients) {
            return [
                'text' => 'no clients',
                'data' => false,
            ];
        }

        $name = $this->bot->getName($clients[$client]['interface']);
        $config = htmlspecialchars($this->bot->createConfig($clients[$client]));
        $shortLink = $this->bot->getWGType() === 'awg' ? $this->bot->getAmneziaShortLink($clients[$client]) : null;

        return [
            'text' => "<pre>{$config}</pre>\n\n<code>{$shortLink}</code>\n\n<b>{$name}</b> ({$this->title()})",
            'data' => [
                [
                    [
                        'text' => $this->bot->i18n('rename'),
                        'callback_data' => "/rename {$client}_{$page}",
                    ],
                    [
                        'text' => $this->bot->i18n('timer'),
                        'callback_data' => "/timer {$client}_{$page}",
                    ],
                ],
                [
                    [
                        'text' => $this->bot->i18n('show QR'),
                        'callback_data' => "/qr {$client}",
                    ],
                    [
                        'text' => $this->bot->i18n('download config'),
                        'callback_data' => "/download {$client}",
                    ],
                ],
                [
                    [
                        'text' => $this->bot->i18n($clients[$client]['# off'] ? 'off' : 'on'),
                        'callback_data' => "/switchClient {$client}_{$page}",
                    ],
                    [
                        'text' => $this->bot->i18n($clients[$client]['interface']['DNS'] ? 'delete internal dns' : 'set internal dns'),
                        'callback_data' => '/' . ($clients[$client]['interface']['DNS'] ? 'delete' : '') . "dns {$client}_{$page}",
                    ],
                ],
                [[
                    'text' => $this->bot->i18n('AllowedIPs'),
                    'callback_data' => "/changeAllowedIps {$client}_{$page}",
                ]],
                [[
                    'text' => $this->bot->i18n('MTU') . ' ' . ($clients[$client]['interface']['MTU'] ?: $this->bot->getPacConf()[$this->bot->getInstanceWG(1) . 'mtu'] ?: $this->bot->mtu),
                    'callback_data' => "/changeMTU {$client}_{$page}",
                ]],
                [
                    [
                        'text' => $this->bot->i18n('delete'),
                        'callback_data' => "/delete {$client}_{$page}",
                    ],
                    [
                        'text' => $this->bot->i18n('back'),
                        'callback_data' => "/menu wg {$page}",
                    ],
                ],
            ],
        ];
    }

    public function linkVless(int $xrayClient, int $wireGuardClient, int $page = 0): void
    {
        $xray = $this->bot->getXray();
        $wireGuardClients = $this->bot->readClients();
        $privateKey = $wireGuardClients[$wireGuardClient]['interface']['PrivateKey'];

        foreach ($xray['inbounds'][0]['settings']['clients'] as $index => $client) {
            if ($index === $xrayClient) {
                $xray['inbounds'][0]['settings']['clients'][$index]['awg'] = $privateKey;
            } elseif (! empty($client['awg']) && $client['awg'] === $privateKey) {
                unset($xray['inbounds'][0]['settings']['clients'][$index]['awg']);
            }
        }

        $this->bot->restartXray($xray, 1);
        $this->bot->menu('wg', $page);
    }

    public function unlinkVless(int $wireGuardClient, int $page = 0): void
    {
        $xray = $this->bot->getXray();
        $wireGuardClients = $this->bot->readClients();
        $privateKey = $wireGuardClients[$wireGuardClient]['interface']['PrivateKey'];

        foreach ($xray['inbounds'][0]['settings']['clients'] as $index => $client) {
            if (! empty($client['awg']) && $client['awg'] === $privateKey) {
                unset($xray['inbounds'][0]['settings']['clients'][$index]['awg']);
            }
        }

        $this->bot->restartXray($xray, 1);
        $this->bot->menu('wg', $page);
    }

    public function chooseVless(int $wireGuardClient, int $wgPage = 0, int $xrayPage = 0): void
    {
        $xray = $this->bot->getXray();
        $text[] = 'Menu -> ' . $this->bot->i18n('link awg');
        $data[] = [[
            'text' => $this->bot->i18n('delete'),
            'callback_data' => "/unsetVlessLink {$wireGuardClient}_{$wgPage}",
        ]];

        $clients = array_filter($xray['inbounds'][0]['settings']['clients'], static fn (array $client): bool => empty($client['off']));
        uasort($clients, static fn (array $left, array $right): int => ($left['time'] ?: PHP_INT_MAX) <=> ($right['time'] ?: PHP_INT_MAX));

        $pages = (int) ceil(count($clients) / $this->bot->limit);
        $xrayPage = min($xrayPage, $pages - 1);
        $xrayPage = $xrayPage === -2 ? $pages - 1 : $xrayPage;
        $clients = $xrayPage !== -1 ? array_slice($clients, $xrayPage * $this->bot->limit, $this->bot->limit, true) : $clients;

        foreach ($clients as $index => $client) {
            $data[] = [[
                'text' => $client['email'],
                'callback_data' => "/setVlessLink {$index}_{$wireGuardClient}_{$wgPage}",
            ]];
        }

        if ($xrayPage !== -1 && $pages > 1) {
            $data[] = [
                [
                    'text' => '<<',
                    'callback_data' => '/choiceVless ' . $wireGuardClient . '_' . $wgPage . '_' . ($xrayPage - 1 >= 0 ? $xrayPage - 1 : $pages - 1),
                ],
                [
                    'text' => $xrayPage + 1,
                    'callback_data' => "/choiceVless {$wireGuardClient}_{$wgPage}_{$xrayPage}",
                ],
                [
                    'text' => '>>',
                    'callback_data' => '/choiceVless ' . $wireGuardClient . '_' . $wgPage . '_' . ($xrayPage < $pages - 1 ? $xrayPage + 1 : 0),
                ],
            ];
        }

        $data[] = [[
            'text' => $this->bot->i18n('back'),
            'callback_data' => "/menu wg {$wgPage}",
        ]];

        $this->bot->update(
            $this->bot->input['chat'],
            $this->bot->input['message_id'],
            implode("\n", $text ?? ['...']),
            $data ?: false,
        );
    }
}
