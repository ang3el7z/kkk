<?php

declare(strict_types=1);

namespace VpnBot\Domain\Feature;

use InvalidArgumentException;

final class FeatureDefinition
{
    /**
     * @param list<string> $services
     * @param list<string> $menuKeys
     */
    public function __construct(
        private readonly string $id,
        private readonly array $services,
        private readonly array $menuKeys = [],
        private readonly bool $toggleable = true,
        private readonly bool $enabledByDefault = true,
    ) {
        if ($this->id === '') {
            throw new InvalidArgumentException('Feature id must not be empty.');
        }

        if ($this->services === []) {
            throw new InvalidArgumentException(sprintf('Feature "%s" must define at least one service.', $this->id));
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return list<string>
     */
    public function services(): array
    {
        return $this->services;
    }

    /**
     * @return list<string>
     */
    public function menuKeys(): array
    {
        return $this->menuKeys;
    }

    public function toggleable(): bool
    {
        return $this->toggleable;
    }

    public function enabledByDefault(): bool
    {
        return $this->enabledByDefault;
    }

    public function hasService(string $service): bool
    {
        return in_array($service, $this->services, true);
    }

    public function matchesMenuKey(string $menuKey): bool
    {
        $normalizedMenuKey = trim($menuKey);

        foreach ($this->menuKeys as $registeredMenuKey) {
            if (
                $normalizedMenuKey === $registeredMenuKey
                || str_starts_with($normalizedMenuKey, $registeredMenuKey . ' ')
            ) {
                return true;
            }
        }

        return false;
    }
}
