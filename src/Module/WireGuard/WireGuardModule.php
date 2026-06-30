<?php

declare(strict_types=1);

namespace VpnBot\Module\WireGuard;

final class WireGuardModule
{
    public function __construct(
        private readonly WireGuardConfigCodec $codec,
        private readonly WireGuardRuntime $runtime,
        private readonly WireGuardClientStore $clientStore,
    ) {
    }

    /**
     * @return array{interface: array<string, string>, peers: array<int, array<string, string>>}
     */
    public function readConfig(string $service): array
    {
        return $this->codec->parseConfig($this->runtime->readConfig($service));
    }

    /**
     * @return array{interface: array<string, string>, peers: array<int, array<string, string>>}
     */
    public function readStatus(string $service, string $binary): array
    {
        return $this->codec->parseStatus($this->runtime->readStatus($service, $binary));
    }

    /**
     * @param array{interface: array<string, string>, peers?: array<int, array<string, string>>} $data
     */
    public function renderConfig(array $data): string
    {
        return $this->codec->renderConfig($data);
    }

    /**
     * @param array<string, string> $peer
     */
    public function resolveClientName(array $peer): string
    {
        return $this->codec->resolveClientName($peer);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readClients(): array
    {
        return $this->clientStore->readAll();
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     */
    public function saveClients(array $clients): void
    {
        $this->clientStore->saveAll($clients);
    }

    /**
     * @param array<string, mixed> $client
     */
    public function saveClient(array $client): int
    {
        $clients = array_merge($this->readClients(), [$client]);
        $this->saveClients($clients);

        return count($clients) - 1;
    }

    public function restart(string $service, string $downBinary, string $upBinary, string $config): bool
    {
        return $this->runtime->applyConfig($service, $downBinary, $upBinary, $config);
    }
}
