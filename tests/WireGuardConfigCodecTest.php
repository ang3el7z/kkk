<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/WireGuard/WireGuardConfigCodec.php';
}

use VpnBot\Module\WireGuard\WireGuardConfigCodec;

$codec = new WireGuardConfigCodec();

$config = <<<'CFG'
[Interface]
Address = 10.0.0.1/24
PrivateKey = server-private

[Peer]
## name = alice
PublicKey = peer-public
AllowedIPs = 10.0.0.2/32
CFG;

$parsedConfig = $codec->parseConfig($config);
assertWireGuard($parsedConfig['interface']['Address'] === '10.0.0.1/24', 'config parser must read interface address');
assertWireGuard($parsedConfig['peers'][0]['## name'] === 'alice', 'config parser must read peer metadata');
assertWireGuard($codec->resolveClientName($parsedConfig['peers'][0]) === 'alice', 'client name resolver must prefer peer name');
$renderedConfig = $codec->renderConfig($parsedConfig);
assertWireGuard($codec->parseConfig($renderedConfig) === $parsedConfig, 'config renderer must preserve parsed config semantics');

$status = <<<'STATUS'
interface: wg0
public key: server-public
listening port: 51820
peer: peer-public
endpoint: 1.2.3.4:51820
allowed ips: 10.0.0.2/32
STATUS;

$parsedStatus = $codec->parseStatus($status);
assertWireGuard($parsedStatus['interface']['public key'] === 'server-public', 'status parser must read interface fields');
assertWireGuard($parsedStatus['peers'][0]['endpoint'] === '1.2.3.4:51820', 'status parser must read peer endpoint');

echo "WireGuardConfigCodecTest: OK\n";

function assertWireGuard(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
