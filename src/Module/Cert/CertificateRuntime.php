<?php
declare(strict_types=1);

namespace VpnBot\Module\Cert;

interface CertificateRuntime
{
    /**
     * @param list<string> $domains
     * @return array{bundle:?string,output:list<string>,exit_code:int}
     */
    public function obtainLetsEncryptBundle(string $primaryDomain, array $domains): array;

    public function loadNginxConfig(): string;
}
