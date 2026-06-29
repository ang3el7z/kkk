<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureDefinition.php';
    require dirname(__DIR__) . '/src/Domain/Feature/FeatureRegistry.php';
    require dirname(__DIR__) . '/src/Infrastructure/Compose/ComposeOverrideWriter.php';
}

use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Infrastructure\Compose\ComposeOverrideWriter;

$outputPath = dirname(__DIR__) . '/tmp/compose-writer-test.override.yml';

@unlink($outputPath);

$writer = new ComposeOverrideWriter(new FeatureRegistry());
$writer->write(
    $outputPath,
    [
        'xray' => false,
        'adguard' => false,
    ],
    [
        'wg' => ['enabled' => true],
        'tg' => ['host_port' => 2443],
        'ad' => ['enabled' => false],
        'dnstt' => false,
    ],
);

$override = file_get_contents($outputPath);

assertCompose($override !== false, 'compose override file must be readable');
assertCompose(str_contains($override, "services:\n"), 'compose override must define services root');
assertCompose(str_contains($override, "xr:\n"), 'disabled xray must have override service');
assertCompose(str_contains($override, "    profiles:\n      - \"disabled-xr\"\n"), 'disabled xray must be profiled out');
assertCompose(str_contains($override, "up:\n    depends_on: !override\n"), 'xray disable must rewrite upstream dependencies');
assertCompose(str_contains($override, "wg:\n    ports:\n      - \"51820:51820/udp\"\n"), 'wireguard default port override must be rendered');
assertCompose(str_contains($override, "tg:\n    ports:\n      - \"2443:443\"\n"), 'mtproto custom host port must be rendered');
assertCompose(str_contains($override, "ad:\n    profiles:\n      - \"disabled-ad\"\n    ports: []\n"), 'disabled adguard must keep profile and empty ports override');
assertCompose(str_contains($override, "dnstt:\n    ports: []\n"), 'dnstt hidden port must render empty ports override');

@unlink($outputPath);

echo "ComposeOverrideWriterTest: OK\n";

function assertCompose(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
