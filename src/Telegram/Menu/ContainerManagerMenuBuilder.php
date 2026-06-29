<?php

declare(strict_types=1);

namespace VpnBot\Telegram\Menu;

use VpnBot\Domain\Feature\FeatureDefinition;
use VpnBot\Domain\Feature\FeatureRegistry;

final class ContainerManagerMenuBuilder
{
    /**
     * @param callable(FeatureDefinition): string $labelResolver
     * @param array<string, bool> $featureStates
     * @return array{text: string, data: array<int, array<int, array<string, string>>>}
     */
    public function build(
        FeatureRegistry $registry,
        array $featureStates,
        callable $labelResolver,
        string $backLabel = 'back',
    ): array {
        $text = ['Settings -> Container manager'];
        $data = [];

        foreach ($registry->all() as $definition) {
            $enabled = $featureStates[$definition->id()] ?? $definition->enabledByDefault();
            $status = $enabled ? '🟢' : '🔴';
            $label = $labelResolver($definition);

            if ($definition->toggleable()) {
                $text[] = sprintf('%s %s', $status, $label);
                $data[] = [[
                    'text' => sprintf('%s %s', $status, $label),
                    'callback_data' => '/featureToggle ' . $definition->id(),
                ]];

                continue;
            }

            $text[] = sprintf('🔒 %s', $label);
            $data[] = [[
                'text' => sprintf('🔒 %s', $label),
                'callback_data' => '/menu containers',
            ]];
        }

        $data[] = [[
            'text' => $backLabel,
            'callback_data' => '/menu config',
        ]];

        return [
            'text' => implode("\n", $text),
            'data' => $data,
        ];
    }
}
