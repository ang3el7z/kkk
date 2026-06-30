<?php

declare(strict_types=1);

namespace VpnBot\Application\Feature;

use RuntimeException;
use Throwable;
use VpnBot\Domain\Feature\FeatureDefinition;
use VpnBot\Domain\Feature\FeatureRegistry;

final class ContainerManagerService
{
    public function __construct(
        private readonly FeatureRegistry $registry,
        private readonly ?FeatureManager $manager,
        private readonly ContainerRuntime $runtime,
    ) {
    }

    /**
     * @return array<string, bool>
     */
    public function featureStates(): array
    {
        if ($this->manager !== null) {
            return $this->manager->list();
        }

        $states = [];

        foreach ($this->registry->all() as $definition) {
            $states[$definition->id()] = $definition->enabledByDefault();
        }

        return $states;
    }

    /**
     * @return array<string, string>
     */
    public function runtimeStates(): array
    {
        $states = [];
        $services = [];

        foreach ($this->registry->all() as $definition) {
            $states[$definition->id()] = 'unknown';

            foreach ($definition->services() as $service) {
                $services[$service] = $service;
            }
        }

        try {
            $serviceStates = $this->runtime->status(array_values($services));

            foreach ($this->registry->all() as $definition) {
                $states[$definition->id()] = $this->aggregateRuntimeState($definition, $serviceStates);
            }
        } catch (Throwable) {
        }

        return $states;
    }

    /**
     * @return array{0: FeatureDefinition, 1: bool, 2: string}
     */
    public function resolveToggleState(string $featureId, ?string $requestedAction = null): array
    {
        $definition = $this->registry->get($featureId);

        if (! $definition->toggleable()) {
            throw new RuntimeException('Feature is locked.');
        }

        if ($this->manager === null) {
            throw new RuntimeException('Feature manager unavailable');
        }

        $states = $this->manager->list();
        $enabled = $states[$featureId] ?? null;

        if (! is_bool($enabled)) {
            throw new RuntimeException('Unknown feature.');
        }

        $expectedAction = $enabled ? 'disable' : 'enable';

        if ($requestedAction === null) {
            return [$definition, $enabled, $expectedAction];
        }

        if (! in_array($requestedAction, ['enable', 'disable'], true)) {
            throw new RuntimeException('Unknown toggle action.');
        }

        if ($requestedAction !== $expectedAction) {
            throw new RuntimeException('State changed. Reload container manager and try again.');
        }

        return [$definition, $enabled, $requestedAction];
    }

    /**
     * @param array<string, string> $serviceStates
     */
    private function aggregateRuntimeState(FeatureDefinition $definition, array $serviceStates): string
    {
        $featureStates = [];

        foreach ($definition->services() as $service) {
            $featureStates[] = $serviceStates[$service] ?? 'unknown';
        }

        if (in_array('running', $featureStates, true)) {
            return 'running';
        }

        if (in_array('stopped', $featureStates, true)) {
            return 'stopped';
        }

        if ($featureStates !== [] && count(array_unique($featureStates)) === 1 && $featureStates[0] === 'missing') {
            return 'missing';
        }

        return 'unknown';
    }
}
