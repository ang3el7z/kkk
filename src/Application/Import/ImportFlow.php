<?php

declare(strict_types=1);

namespace VpnBot\Application\Import;

final class ImportFlow
{
    public function __construct(
        private readonly object $bot,
    ) {
    }

    public function promptImport(): void
    {
        $reply = $this->bot->send(
            $this->bot->input['chat'],
            "@{$this->bot->input['username']} send the export file:",
            $this->bot->input['message_id'],
            reply: 'send the export file:',
        );
        $_SESSION['reply'][$reply['result']['message_id']] = [
            'start_message' => $this->bot->input['message_id'],
            'start_callback' => $this->bot->input['callback_id'],
            'callback' => 'importFile',
            'args' => [],
        ];
    }

    public function importFile(string|false $file = false): void
    {
        $payload = $this->loadPayload($file);

        if (empty($payload) || ! is_array($payload)) {
            $this->bot->answer($this->bot->input['callback_id'], 'error', true);

            return;
        }

        $state = [
            'switch_amnezia' => 0,
            'switch_wg1amnezia' => 0,
        ];
        $out = [];

        $this->importCertificates($payload, $out);
        $this->importPac($payload, $out, $state);
        $this->importWireGuard($payload, $out, $state);
        $this->importAdguard($payload, $out);
        $this->importShadowsocks($payload, $out);
        $this->importMtproto($payload, $out);
        $this->importHwid($payload, $out);
        $this->importXray($payload, $out);
        $this->importOpenConnect($payload, $out);
        $this->importHysteria($payload, $out);
        $this->importDnstt($payload, $out);
        $this->finalizeImport($payload, $out);

        $this->bot->language = $this->bot->getPacConf()['language'] ?: 'en';
        $this->bot->limit = $this->bot->getPacConf()['limitpage'] ?: 5;

        if ($file === false) {
            sleep(3);
            $this->bot->menu();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPayload(string|false $file): ?array
    {
        if ($file !== false) {
            $contents = file_get_contents($file);

            return is_string($contents) ? json_decode($contents, true) : null;
        }

        $response = $this->bot->request('getFile', ['file_id' => $this->bot->input['file_id']]);
        $contents = file_get_contents($this->bot->file . $response['result']['file_path']);

        return is_string($contents) ? json_decode($contents, true) : null;
    }

    /**
     * @param array<int, string> $out
     */
    private function progress(array &$out, string $message): void
    {
        $out[] = $message;
        $this->bot->update($this->bot->input['chat'], $this->bot->input['message_id'], implode("\n", $out));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importCertificates(array $payload, array &$out): void
    {
        if (empty($payload['ssl'])) {
            return;
        }

        $this->progress($out, 'update certificates');
        $this->bot->buildCertificateModule()->saveCertificatePair($payload['ssl']);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     * @param array<string, int> $state
     */
    private function importPac(array $payload, array &$out, array &$state): void
    {
        if (empty($payload['pac'])) {
            return;
        }

        $this->progress($out, 'update pac');

        if ($this->bot->getPacConf()['amnezia'] != $payload['pac']['amnezia']) {
            $state['switch_amnezia'] = 1;
        }

        if ($this->bot->getPacConf()['wg1_amnezia'] != $payload['pac']['wg1_amnezia']) {
            $state['switch_wg1amnezia'] = 1;
        }

        $this->bot->setPacConf($payload['pac']);
        $this->progress($out, 'update naiveproxy');
        $this->bot->restartNaive();
        $this->bot->pacUpdate('1');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     * @param array<string, int> $state
     */
    private function importWireGuard(array $payload, array &$out, array $state): void
    {
        if (! empty($payload['wg'])) {
            $this->progress($out, 'update wireguard');
            $this->bot->wg = 0;
            $this->bot->saveClients($payload['wg']['clients']);
            $this->bot->restartWG($this->bot->createConfig($payload['wg']['server']), $state['switch_amnezia']);
            $this->bot->iptablesWG();
        }

        if (! empty($payload['wg1'])) {
            $this->progress($out, 'update wireguard 1');
            $this->bot->wg = 1;
            $this->bot->saveClients($payload['wg1']['clients']);
            $this->bot->restartWG($this->bot->createConfig($payload['wg1']['server']), $state['switch_wg1amnezia']);
            $this->bot->iptablesWG();
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importAdguard(array $payload, array &$out): void
    {
        if (empty($payload['ad'])) {
            return;
        }

        $this->progress($out, 'update adguard');
        $this->bot->stopAd();
        yaml_emit_file($this->bot->adguard, $payload['ad']);
        $this->bot->startAd();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importShadowsocks(array $payload, array &$out): void
    {
        if (! empty($payload['ss'])) {
            $this->progress($out, 'update shadowsocks server');
            $this->bot->buildShadowsocksModule()->saveServerAndRestart($payload['ss']);
        }

        if (! empty($payload['sl'])) {
            $this->progress($out, 'update shadowsocks proxy');
            $this->bot->buildShadowsocksModule()->saveLocalAndRestart($payload['sl']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importMtproto(array $payload, array &$out): void
    {
        if (empty($payload['mtproto'])) {
            return;
        }

        $this->progress($out, 'update mtproto');
        $this->bot->buildMtprotoModule()->saveConfig([
            'secret' => (string) $payload['mtproto'],
            'domain' => (string) ($payload['mtprotodomain'] ?? ''),
            'adtag' => (string) trim($payload['mtprotoadtag'] ?? ''),
        ]);
        $this->bot->restartTG();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importHwid(array $payload, array &$out): void
    {
        if (! array_key_exists('hwid', $payload)) {
            return;
        }

        $this->progress($out, 'update hwid devices');
        $data = is_array($payload['hwid']) ? $payload['hwid'] : [];
        file_put_contents($this->bot->hwid, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importXray(array $payload, array &$out): void
    {
        if (! empty($payload['xray'])) {
            $this->progress($out, 'update xray');
            $this->bot->restartXray($payload['xray']);
            $this->bot->adguardXrayClients();
            $this->bot->setUpstreamDomain(
                $payload['pac']['transport'] != 'Reality'
                    ? 't'
                    : ($payload['pac']['reality']['domain'] ?: $payload['xray']['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0])
            );
        }

        if (! empty($payload['xraystats'])) {
            $this->progress($out, 'update xray stats');
            $this->bot->setXrayStats($payload['xraystats']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importOpenConnect(array $payload, array &$out): void
    {
        if (empty($payload['oc'])) {
            return;
        }

        $this->progress($out, 'update ocserv');
        file_put_contents('/config/ocserv.passwd', $payload['ocu']);
        $this->bot->restartOcserv($payload['oc']);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importHysteria(array $payload, array &$out): void
    {
        if (empty($payload['hy'])) {
            return;
        }

        $this->progress($out, 'update hysteria');
        $config = $this->bot->buildHysteriaModule()->syncPassword(
            $payload['hy'],
            (string) ($this->bot->getPacConf()['hysteria_pass'] ?? '')
        );
        $this->bot->buildHysteriaModule()->saveAndRestart(
            $config,
            ! empty($this->bot->getPacConf()['hysteria_pass'])
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function importDnstt(array $payload, array &$out): void
    {
        if (empty($payload['dnstt'])) {
            return;
        }

        $this->progress($out, 'update dnstt certificates');
        $this->bot->buildDnsttModule()->saveKeyPair($payload['dnstt']);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $out
     */
    private function finalizeImport(array $payload, array &$out): void
    {
        if (! empty($payload['pac']['domain'])) {
            $this->bot->setUpstreamDomainOcserv($payload['pac']['domain']);
            $this->bot->setUpstreamDomainNaive($payload['pac']['domain']);
        }

        $this->progress($out, 'reset nginx');
        $this->bot->cloakNginx();
        $this->progress($out, 'end import');
    }
}
