<?php

declare(strict_types=1);

namespace VpnBot\Domain\Feature;

interface FeatureRepository
{
    public function isEnabled(string $featureId): bool;

    public function setEnabled(string $featureId, bool $enabled): void;

    /**
     * @return array<string, bool>
     */
    public function all(): array;
}
