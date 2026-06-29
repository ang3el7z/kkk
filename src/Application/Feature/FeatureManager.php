<?php

declare(strict_types=1);

namespace VpnBot\Application\Feature;

use Throwable;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Domain\Feature\FeatureRepository;
use VpnBot\Infrastructure\Compose\ComposeOverrideWriter;

final class FeatureManager
{
    /**
     * @param array<string, mixed> $portStates
     */
    public function __construct(
        private readonly FeatureRepository $repository,
        private readonly FeatureRegistry $registry,
        private readonly ComposeOverrideWriter $composeOverrideWriter,
        private readonly ContainerRuntime $containerRuntime,
        private readonly string $composeOverridePath,
        private readonly array $portStates = [],
    ) {
    }

    public function enable(string $featureId): void
    {
        $this->transition($featureId, true);
    }

    public function disable(string $featureId): void
    {
        $this->transition($featureId, false);
    }

    /**
     * @return array<string, bool>
     */
    public function list(): array
    {
        return $this->repository->all();
    }

    private function transition(string $featureId, bool $enabled): void
    {
        $definition = $this->registry->get($featureId);
        $before = $this->repository->all();
        $current = $before[$featureId];

        if ($current === $enabled) {
            $this->composeOverrideWriter->write($this->composeOverridePath, $before, $this->portStates);

            return;
        }

        $changed = false;

        try {
            $this->repository->setEnabled($featureId, $enabled);
            $changed = true;

            $after = $this->repository->all();
            $this->composeOverrideWriter->write($this->composeOverridePath, $after, $this->portStates);

            if ($enabled) {
                $this->containerRuntime->start($definition->services());

                return;
            }

            $this->containerRuntime->stopAndRemove($definition->services());
        } catch (Throwable $exception) {
            if ($changed) {
                $this->repository->setEnabled($featureId, $current);
                $this->composeOverrideWriter->write($this->composeOverridePath, $before, $this->portStates);
            }

            throw $exception;
        }
    }
}
