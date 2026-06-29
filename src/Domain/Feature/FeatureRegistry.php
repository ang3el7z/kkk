<?php

declare(strict_types=1);

namespace VpnBot\Domain\Feature;

use InvalidArgumentException;

final class FeatureRegistry
{
    /**
     * @var array<string, FeatureDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<string, string>
     */
    private array $serviceIndex = [];

    public function __construct()
    {
        foreach ($this->defaultDefinitions() as $definition) {
            $this->register($definition);
        }
    }

    /**
     * @return list<FeatureDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function get(string $featureId): FeatureDefinition
    {
        if (! isset($this->definitions[$featureId])) {
            throw new InvalidArgumentException(sprintf('Unknown feature: %s', $featureId));
        }

        return $this->definitions[$featureId];
    }

    public function findByService(string $service): ?FeatureDefinition
    {
        $featureId = $this->serviceIndex[$service] ?? null;

        return $featureId !== null ? $this->definitions[$featureId] : null;
    }

    public function findByMenuKey(string $menuKey): ?FeatureDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->matchesMenuKey($menuKey)) {
                return $definition;
            }
        }

        return null;
    }

    private function register(FeatureDefinition $definition): void
    {
        $featureId = $definition->id();

        if (isset($this->definitions[$featureId])) {
            throw new InvalidArgumentException(sprintf('Feature "%s" is already registered.', $featureId));
        }

        foreach ($definition->services() as $service) {
            if (isset($this->serviceIndex[$service])) {
                throw new InvalidArgumentException(sprintf(
                    'Service "%s" is already assigned to feature "%s".',
                    $service,
                    $this->serviceIndex[$service]
                ));
            }
        }

        $this->definitions[$featureId] = $definition;

        foreach ($definition->services() as $service) {
            $this->serviceIndex[$service] = $featureId;
        }
    }

    /**
     * @return list<FeatureDefinition>
     */
    private function defaultDefinitions(): array
    {
        return [
            new FeatureDefinition('php', ['php'], [], false),
            new FeatureDefinition('service', ['service'], [], false),
            new FeatureDefinition('ng', ['ng'], [], false),
            new FeatureDefinition('up', ['up'], [], false),
            new FeatureDefinition('wireguard', ['wg'], ['/menu wg 0', '/changePort wg']),
            new FeatureDefinition('wireguard_1', ['wg1'], ['/menu wg 1', '/changePort wg1']),
            new FeatureDefinition('xray', ['xr'], ['/xray']),
            new FeatureDefinition('openconnect', ['oc'], ['/menu oc']),
            new FeatureDefinition('naive', ['np'], ['/menu naive']),
            new FeatureDefinition('warp', ['wp'], ['/warp']),
            new FeatureDefinition('proxy', ['proxy'], ['/proxy']),
            new FeatureDefinition('shadowsocks', ['ss'], ['/menu ss', '/changePort ss']),
            new FeatureDefinition('dnstt', ['dnstt'], ['/dnstt', '/hidePort dnstt']),
            new FeatureDefinition('hysteria', ['hy'], ['/menu hy', '/changePort hy']),
            new FeatureDefinition('adguard', ['ad'], ['/menu adguard', '/hidePort ad']),
            new FeatureDefinition('mtproto', ['tg'], ['/mtproto', '/changePort tg']),
        ];
    }
}
