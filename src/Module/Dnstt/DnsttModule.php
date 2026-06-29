<?php
declare(strict_types=1);

namespace VpnBot\Module\Dnstt;

final class DnsttModule
{
    public function __construct(
        private readonly DnsttKeyStore $store,
        private readonly DnsttRuntime $runtime,
    ) {
    }

    /**
     * @return array{private:string,public:string}|false
     */
    public function loadKeyPair(): array|false
    {
        return $this->store->load();
    }

    /**
     * @param array{private:string,public:string} $pair
     */
    public function saveKeyPair(array $pair): void
    {
        $this->store->save($pair);
    }

    public function publicKeyPath(): string
    {
        return $this->store->publicKeyPath();
    }

    public function restart(string $domain, string $password): void
    {
        $this->runtime->stop();

        if ($domain === '' || $password === '') {
            return;
        }

        $this->runtime->ensureUserPassword('vpnbot', $password);

        if ($this->store->load() === false) {
            $this->runtime->generateKeyPair();
        }

        $this->runtime->start($domain);
    }

    /**
     * @return array{instructions:string,account:string,server_name:string,public_key:string}|null
     */
    public function buildMenuState(string $domain, string $password, string $rootDomain, string $ip): ?array
    {
        if ($domain === '' || $password === '') {
            return null;
        }

        $pair = $this->store->load();

        return [
            'instructions' => sprintf(
                "set the NS record for %s: tns.%s\nset A record for tns.%s: %s",
                $domain,
                $rootDomain,
                $rootDomain,
                $ip
            ),
            'account' => 'vpnbot:' . $password,
            'server_name' => $domain,
            'public_key' => $pair !== false ? $pair['public'] : '',
        ];
    }
}
