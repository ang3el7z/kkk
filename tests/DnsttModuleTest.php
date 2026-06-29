<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/Dnstt/DnsttKeyStore.php';
    require dirname(__DIR__) . '/src/Module/Dnstt/DnsttModule.php';
    require dirname(__DIR__) . '/src/Module/Dnstt/DnsttRuntime.php';
}

use VpnBot\Module\Dnstt\DnsttKeyStore;
use VpnBot\Module\Dnstt\DnsttModule;
use VpnBot\Module\Dnstt\DnsttRuntime;

$dir = dirname(__DIR__) . '/tmp/dnstt-module';

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$runtime = new class () implements DnsttRuntime {
    /** @var list<string> */
    public array $calls = [];

    public function stop(): string
    {
        $this->calls[] = 'stop';

        return 'stop';
    }

    public function ensureUserPassword(string $username, string $password): string
    {
        $this->calls[] = "user:$username:$password";

        return 'user';
    }

    public function generateKeyPair(): string
    {
        $this->calls[] = 'generate';

        return 'generate';
    }

    public function start(string $domain): string
    {
        $this->calls[] = "start:$domain";

        return 'start';
    }
};

$store = new DnsttKeyStore($dir);
$module = new DnsttModule($store, $runtime);

assertDnstt($module->loadKeyPair() === false, 'module must report missing key pair');

$module->restart('ns.example.org', 'secret');
assertDnstt($runtime->calls === ['stop', 'user:vpnbot:secret', 'generate', 'start:ns.example.org'], 'restart must stop, provision user, generate keys when missing, and start');

$module->saveKeyPair(['private' => 'priv', 'public' => 'pub']);
$pair = $module->loadKeyPair();
assertDnstt($pair !== false && $pair['public'] === 'pub', 'saveKeyPair must persist key pair');

$runtime->calls = [];
$module->restart('ns.example.org', 'secret');
assertDnstt($runtime->calls === ['stop', 'user:vpnbot:secret', 'start:ns.example.org'], 'restart must skip key generation when keys already exist');

$state = $module->buildMenuState('ns.example.org', 'secret', 'example.org', '1.2.3.4');
assertDnstt($state !== null && str_contains($state['instructions'], 'tns.example.org'), 'buildMenuState must render DNS instructions');
assertDnstt($state !== null && $state['account'] === 'vpnbot:secret', 'buildMenuState must render account');
assertDnstt($state !== null && $state['public_key'] === 'pub', 'buildMenuState must include public key');

$runtime->calls = [];
$module->restart('', 'secret');
assertDnstt($runtime->calls === ['stop'], 'restart must stop only when domain missing');
assertDnstt($module->buildMenuState('', 'secret', 'example.org', '1.2.3.4') === null, 'buildMenuState must return null when config incomplete');

@unlink($dir . '/server.key');
@unlink($dir . '/server.pub');
@rmdir($dir);

echo "DnsttModuleTest: OK\n";

function assertDnstt(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
