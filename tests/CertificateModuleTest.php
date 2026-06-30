<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Module/Cert/CertificateStore.php';
    require dirname(__DIR__) . '/src/Module/Cert/CertificateModule.php';
    require dirname(__DIR__) . '/src/Module/Cert/CertificateRuntime.php';
}

use VpnBot\Module\Cert\CertificateModule;
use VpnBot\Module\Cert\CertificateRuntime;
use VpnBot\Module\Cert\CertificateStore;

$dir = dirname(__DIR__) . '/tmp/certificate-module';

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$privatePath = $dir . '/cert_private';
$publicPath = $dir . '/cert_public';

$runtime = new class () implements CertificateRuntime {
    public function obtainLetsEncryptBundle(string $primaryDomain, array $domains): array
    {
        return ['bundle' => null, 'output' => [], 'exit_code' => 0];
    }

    public function loadNginxConfig(): string
    {
        return "#~letsencrypt\n";
    }
};

$module = new CertificateModule(new CertificateStore($privatePath, $publicPath), $runtime);

$domains = $module->buildLetsEncryptDomains('example.org', 'dns', 'oc', 'np');
assertCertificate($domains === ['example.org', 'oc.example.org', 'np.example.org', 'dns.example.org'], 'buildLetsEncryptDomains must include enabled service domains');

$bundle = <<<PEM
-----BEGIN PRIVATE KEY-----
abc
-----END PRIVATE KEY-----
-----BEGIN CERTIFICATE-----
pub
-----END CERTIFICATE-----
PEM;

$pair = $module->splitBundle($bundle);
assertCertificate($pair !== null && str_contains($pair['private'], 'BEGIN PRIVATE KEY'), 'splitBundle must extract private key');
assertCertificate($pair !== null && str_contains($pair['public'], 'BEGIN CERTIFICATE'), 'splitBundle must extract public bundle');
assertCertificate($module->splitBundle('bad bundle') === null, 'splitBundle must reject malformed bundle');

$module->saveCertificatePair($pair);
$loaded = $module->loadCertificatePair();
assertCertificate($loaded !== false && $loaded['public'] === $pair['public'], 'saveCertificatePair must persist bundle');

assertCertificate($module->parseCertificateType("#~self\nserver {}") === 'self', 'parseCertificateType must parse marker');

$module->deleteCertificatePair();
assertCertificate($module->loadCertificatePair() === false, 'deleteCertificatePair must remove bundle');

@unlink($privatePath);
@unlink($publicPath);
@rmdir($dir);

echo "CertificateModuleTest: OK\n";

function assertCertificate(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
