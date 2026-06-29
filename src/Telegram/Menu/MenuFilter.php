<?php

declare(strict_types=1);

namespace VpnBot\Telegram\Menu;

use Throwable;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Domain\Feature\FeatureRepository;

final class MenuFilter
{
    public function __construct(
        private readonly FeatureRegistry $registry,
        private readonly ?FeatureRepository $repository = null,
    ) {
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $keyboard
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function filter(array $keyboard): array
    {
        if ($this->repository === null) {
            return $keyboard;
        }

        try {
            $filteredKeyboard = [];

            foreach ($keyboard as $row) {
                $filteredRow = [];

                foreach ($row as $button) {
                    if ($this->shouldKeepButton($button)) {
                        $filteredRow[] = $button;
                    }
                }

                if ($filteredRow !== []) {
                    $filteredKeyboard[] = $filteredRow;
                }
            }

            return $filteredKeyboard;
        } catch (Throwable) {
            return $keyboard;
        }
    }

    /**
     * @param array<string, mixed> $button
     */
    private function shouldKeepButton(array $button): bool
    {
        $menuKey = $button['callback_data'] ?? null;

        if (! is_string($menuKey)) {
            return true;
        }

        $definition = $this->registry->findByMenuKey($menuKey);

        if ($definition === null) {
            return true;
        }

        return $this->repository->isEnabled($definition->id());
    }
}
