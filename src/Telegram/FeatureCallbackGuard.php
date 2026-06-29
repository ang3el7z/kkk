<?php

declare(strict_types=1);

namespace VpnBot\Telegram;

use Throwable;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Domain\Feature\FeatureRepository;

final class FeatureCallbackGuard
{
    public function __construct(
        private readonly FeatureRegistry $registry,
        private readonly ?FeatureRepository $repository = null,
    ) {
    }

    public function isAllowed(?string $menuKey): bool
    {
        if ($menuKey === null || trim($menuKey) === '' || $this->repository === null) {
            return true;
        }

        try {
            $definition = $this->registry->findByMenuKey($menuKey);

            if ($definition === null) {
                return true;
            }

            return $this->repository->isEnabled($definition->id());
        } catch (Throwable) {
            return true;
        }
    }
}
