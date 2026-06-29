<?php

declare(strict_types=1);

namespace VpnBot\Module\Pac;

use RuntimeException;

final class SubscriptionModule
{
    public function __construct(
        private readonly PacTemplateStore $templateStore,
    ) {
    }

    /**
     * @param array<string, mixed> $xrayConfig
     * @return array{index:int, client: array<string, mixed>}
     */
    public function findClientByUuid(array $xrayConfig, string $uuid): array
    {
        $clients = $xrayConfig['inbounds'][0]['settings']['clients'] ?? [];

        if (! is_array($clients)) {
            throw new RuntimeException('Xray client list is unavailable.');
        }

        foreach ($clients as $index => $client) {
            if (is_array($client) && ($client['id'] ?? null) === $uuid) {
                return [
                    'index' => (int) $index,
                    'client' => $client,
                ];
            }
        }

        throw new RuntimeException(sprintf('Xray client not found: %s', $uuid));
    }

    /**
     * @param array<string, mixed> $client
     * @return array<string, mixed>
     */
    public function resolveTemplateForClient(string $subscriptionType, array $client): array
    {
        $templateKey = match ($subscriptionType) {
            's' => 'v2raytemplate',
            'si' => 'singtemplate',
            'cl' => 'clashtemplate',
            default => throw new RuntimeException(sprintf('Unsupported subscription type: %s', $subscriptionType)),
        };

        $originType = $this->originType($subscriptionType);
        $encodedName = isset($client[$templateKey]) && is_string($client[$templateKey]) && $client[$templateKey] !== ''
            ? (string) $client[$templateKey]
            : null;

        return $this->templateStore->resolveTemplateDocument($originType, $encodedName);
    }

    /**
     * @param array<string, mixed> $client
     */
    public function updateClientTemplate(array $xrayConfig, string $originType, int $index, ?string $encodedName): array
    {
        $key = $originType . 'template';

        if (! isset($xrayConfig['inbounds'][0]['settings']['clients'][$index]) || ! is_array($xrayConfig['inbounds'][0]['settings']['clients'][$index])) {
            throw new RuntimeException(sprintf('Xray client index not found: %d', $index));
        }

        if ($encodedName === null || $encodedName === '') {
            unset($xrayConfig['inbounds'][0]['settings']['clients'][$index][$key]);
        } else {
            $xrayConfig['inbounds'][0]['settings']['clients'][$index][$key] = $encodedName;
        }

        return $xrayConfig;
    }

    public function originType(string $subscriptionType): string
    {
        return match ($subscriptionType) {
            's', 'v2ray' => 'v2ray',
            'si', 'sing' => 'sing',
            'cl', 'clash' => 'clash',
            default => throw new RuntimeException(sprintf('Unsupported template type: %s', $subscriptionType)),
        };
    }
}
