<?php

declare(strict_types=1);

namespace VpnBot\Telegram\Menu;

final class ConfigMenuBuilder
{
    /**
     * @param list<string> $textLines
     * @param list<int|string> $adminIds
     * @return array{text: string, data: array<int, array<int, array<string, string>>>}
     */
    public function build(
        array $textLines,
        bool $hasDomain,
        ?string $domain,
        string $deleteLabel,
        string $installDomainLabel,
        string $nipIoLabel,
        ?string $certificateMode,
        string $renewSslLabel,
        string $deleteSslLabel,
        string $letsencryptSslLabel,
        string $selfSslLabel,
        string $portsLabel,
        string $logsLabel,
        string $ipBanLabel,
        string $langLabel,
        string $pageLabel,
        int $pageLimit,
        string $exportLabel,
        string $importLabel,
        string $backupLabel,
        string $backupDisplay,
        string $autoupdateLabel,
        string $autoupdateStateLabel,
        string $containerManagerLabel,
        string $branchesLabel,
        string $restartLabel,
        string $addLabel,
        string $adminLabel,
        array $adminIds,
        string $backLabel
    ): array {
        $data = [[
            [
                'text' => $hasDomain && $domain !== null ? "$deleteLabel $domain" : $installDomainLabel,
                'callback_data' => $hasDomain ? '/deldomain' : '/domain',
            ],
            [
                'text' => $nipIoLabel,
                'callback_data' => '/addNipdomain',
            ],
        ]];

        if ($hasDomain) {
            if ($certificateMode === 'letsencrypt') {
                $data[] = [[
                    'text' => $renewSslLabel,
                    'callback_data' => '/setSSL letsencrypt',
                ], [
                    'text' => $deleteSslLabel,
                    'callback_data' => '/deletessl',
                ]];
            } elseif ($certificateMode === 'self') {
                $data[] = [[
                    'text' => $deleteSslLabel,
                    'callback_data' => '/deletessl',
                ]];
            } elseif ($certificateMode === null) {
                $data[] = [[
                    'text' => $letsencryptSslLabel,
                    'callback_data' => '/setSSL letsencrypt',
                ], [
                    'text' => $selfSslLabel,
                    'callback_data' => '/selfssl',
                ]];
            }
        }

        $data[] = [[
            'text' => $portsLabel,
            'callback_data' => '/ports',
        ], [
            'text' => $logsLabel,
            'callback_data' => '/logs',
        ], [
            'text' => $ipBanLabel,
            'callback_data' => '/ipMenu',
        ]];
        $data[] = [[
            'text' => $langLabel,
            'callback_data' => '/menu lang',
        ], [
            'text' => $pageLabel . ': ' . $pageLimit,
            'callback_data' => '/enterPage',
        ]];
        $data[] = [[
            'text' => $exportLabel,
            'callback_data' => '/export',
        ], [
            'text' => $importLabel,
            'callback_data' => '/import',
        ]];
        $data[] = [[
            'text' => $backupLabel . ': ' . $backupDisplay,
            'callback_data' => '/backup',
        ]];
        $data[] = [[
            'text' => $autoupdateLabel . ': ' . $autoupdateStateLabel,
            'callback_data' => '/autoupdate',
        ]];
        $data[] = [[
            'text' => $containerManagerLabel,
            'callback_data' => '/menu containers',
        ]];
        $data[] = [[
            'text' => $branchesLabel,
            'callback_data' => '/menu update',
        ], [
            'text' => $restartLabel,
            'callback_data' => '/restart',
        ]];
        $data[] = [[
            'text' => $addLabel . ' ' . $adminLabel,
            'callback_data' => '/addadmin',
        ]];

        foreach ($adminIds as $adminId) {
            $data[] = [[
                'text' => $deleteLabel . ' ' . $adminId,
                'callback_data' => '/deladmin ' . $adminId,
            ]];
        }

        $data[] = [[
            'text' => $backLabel,
            'callback_data' => '/menu',
        ]];

        return [
            'text' => implode("\n", $textLines),
            'data' => $data,
        ];
    }
}
