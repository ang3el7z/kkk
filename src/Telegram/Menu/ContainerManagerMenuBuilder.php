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
     * @param array<string, string> $runtimeStates
     * @return array{text: string, data: array<int, array<int, array<string, string>>>}
     */
    public function build(
        FeatureRegistry $registry,
        array $featureStates,
        array $runtimeStates,
        callable $labelResolver,
        string $backLabel = 'back',
        string $refreshLabel = 'refresh',
    ): array {
        $text = ['Settings -> Container manager'];
        $data = [];
        $warnings = [];

        foreach ($registry->all() as $definition) {
            $enabled = $featureStates[$definition->id()] ?? $definition->enabledByDefault();
            $runtime = $runtimeStates[$definition->id()] ?? 'unknown';
            $status = $enabled ? '🟢' : '🔴';
            $label = $labelResolver($definition);
            $dbState = $enabled ? 'enabled' : 'disabled';
            $line = sprintf('%s %s | DB: %s | Runtime: %s', $status, $label, $dbState, $runtime);

            if ($definition->toggleable()) {
                $text[] = $line;
                $data[] = [[
                    'text' => sprintf('%s %s [%s]', $status, $label, $runtime),
                    'callback_data' => '/featureToggle ' . $definition->id(),
                ]];

                if ($enabled && in_array($runtime, ['stopped', 'missing'], true)) {
                    $warnings[] = sprintf('Warning: %s is enabled in DB but runtime is %s.', $label, $runtime);
                }

                continue;
            }

            $text[] = sprintf('🔒 %s | Runtime: %s', $label, $runtime);
            $data[] = [[
                'text' => sprintf('🔒 %s [%s]', $label, $runtime),
                'callback_data' => '/menu containers',
            ]];
        }

        if ($warnings !== []) {
            $text[] = '';

            foreach ($warnings as $warning) {
                $text[] = '⚠ ' . $warning;
            }
        }

        $data[] = [[
            'text' => $refreshLabel,
            'callback_data' => '/menu containers',
        ]];

        $data[] = [[
            'text' => $backLabel,
            'callback_data' => '/menu config',
        ]];

        return [
            'text' => implode("\n", $text),
            'data' => $data,
        ];
    }

    /**
     * @return array{text: string, data: array<int, array<int, array<string, string>>>}
     */
    public function buildConfirmation(
        FeatureDefinition $definition,
        bool $enabled,
        string $label,
        string $backLabel = 'back',
    ): array {
        $action = $enabled ? 'disable' : 'enable';
        $verb = $enabled ? 'Disable' : 'Enable';
        $status = $enabled ? 'enabled' : 'disabled';

        return [
            'text' => implode("\n", [
                'Settings -> Container manager',
                '',
                sprintf('%s %s?', $verb, $label),
                sprintf('Current state: %s', $status),
                sprintf('Services: %s', implode(', ', $definition->services())),
            ]),
            'data' => [
                [[
                    'text' => sprintf('Confirm %s', strtolower($verb)),
                    'callback_data' => sprintf('/featureToggleConfirm %s %s', $definition->id(), $action),
                ]],
                [[
                    'text' => $backLabel,
                    'callback_data' => '/menu containers',
                ]],
            ],
        ];
    }
}
