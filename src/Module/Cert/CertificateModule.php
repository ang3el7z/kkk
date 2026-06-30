<?php
declare(strict_types=1);

namespace VpnBot\Module\Cert;

final class CertificateModule
{
    public function __construct(
        private readonly CertificateStore $store,
        private readonly CertificateRuntime $runtime,
    ) {
    }

    /**
     * @return array{private:string,public:string}|false
     */
    public function loadCertificatePair(): array|false
    {
        return $this->store->load();
    }

    /**
     * @param array{private:string,public:string} $pair
     */
    public function saveCertificatePair(array $pair): void
    {
        $this->store->save($pair);
    }

    public function deleteCertificatePair(): void
    {
        $this->store->delete();
    }

    /**
     * @return list<string>
     */
    public function buildLetsEncryptDomains(string $primaryDomain, string $adguardKey, string $openConnectSubdomain, string $naiveSubdomain): array
    {
        $domains = [$primaryDomain];

        if ($openConnectSubdomain !== '') {
            $domains[] = $openConnectSubdomain . '.' . $primaryDomain;
        }

        if ($naiveSubdomain !== '') {
            $domains[] = $naiveSubdomain . '.' . $primaryDomain;
        }

        if ($adguardKey !== '') {
            $domains[] = $adguardKey . '.' . $primaryDomain;
        }

        return array_values(array_unique(array_filter($domains)));
    }

    /**
     * @return array{private:string,public:string}|null
     */
    public function splitBundle(string $bundle): ?array
    {
        if (preg_match('~[^\s]+BEGIN PRIVATE KEY.+?END PRIVATE KEY[^\s]+~s', $bundle, $matches) !== 1) {
            return null;
        }

        return [
            'private' => $matches[0],
            'public' => preg_replace('~[^\s]+BEGIN PRIVATE KEY.+?END PRIVATE KEY[^\s]+~s', '', $bundle) ?? '',
        ];
    }

    public function parseCertificateType(string $nginxConfig): ?string
    {
        preg_match('/#~([^\s]+)/', $nginxConfig, $matches);

        return $matches[1] ?? null;
    }

    public function certificateExpiry(): int|false
    {
        $certificate = openssl_x509_read($this->store->publicCertificate());

        if ($certificate === false) {
            return false;
        }

        return openssl_x509_parse($certificate)['validTo_time_t'] ?: false;
    }

    /**
     * @return list<string>|false
     */
    public function certificateDomains(): array|false
    {
        $certificate = openssl_x509_read($this->store->publicCertificate());

        if ($certificate === false) {
            return false;
        }

        $domains = openssl_x509_parse($certificate)['extensions']['subjectAltName'] ?? null;

        if (empty($domains)) {
            return false;
        }

        return array_map(static fn (string $value): string => trim($value), explode(',', str_replace('DNS:', '', $domains)));
    }
}
