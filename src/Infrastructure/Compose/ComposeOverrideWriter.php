<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Compose;

use InvalidArgumentException;
use RuntimeException;
use VpnBot\Domain\Feature\FeatureDefinition;
use VpnBot\Domain\Feature\FeatureRegistry;

final class ComposeOverrideWriter
{
    /**
     * @var array<string, string>
     */
    private const DEFAULT_PORT_BINDINGS = [
        'wg' => '51820:51820/udp',
        'wg1' => '51820:51820/udp',
        'tg' => '443:443',
        'ad' => '853:853',
        'ss' => '8388:8388',
        'dnstt' => '53:53/udp',
        'hy' => '443:443/udp',
    ];

    /**
     * @var array<string, string>
     */
    private const CONTAINER_PORTS = [
        'wg' => '51820/udp',
        'wg1' => '51820/udp',
        'tg' => '443',
        'ad' => '853',
        'ss' => '8388',
        'dnstt' => '53/udp',
        'hy' => '443/udp',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const BASE_DEPENDENCIES = [
        'up' => [
            'ng' => 'service_healthy',
            'xr' => 'service_started',
            'oc' => 'service_started',
            'np' => 'service_started',
        ],
        'ng' => [
            'php' => 'service_healthy',
            'ad' => 'service_started',
            'ss' => 'service_started',
            'xr' => 'service_started',
        ],
        'service' => [
            'up' => 'service_started',
            'ng' => 'service_started',
            'php' => 'service_started',
            'wg' => 'service_started',
            'wg1' => 'service_started',
            'ad' => 'service_started',
            'tg' => 'service_started',
            'xr' => 'service_started',
            'oc' => 'service_started',
            'np' => 'service_started',
            'proxy' => 'service_started',
            'ss' => 'service_started',
            'dnstt' => 'service_started',
            'hy' => 'service_started',
        ],
    ];

    public function __construct(
        private readonly FeatureRegistry $registry,
    ) {
    }

    /**
     * @param array<string, bool> $featureStates
     * @param array<string, mixed> $portStates
     */
    public function render(array $featureStates, array $portStates = []): string
    {
        $services = [];
        $dependencyOverrides = $this->buildDependencyOverrides($featureStates);

        foreach ($this->registry->all() as $definition) {
            foreach ($definition->services() as $service) {
                $override = $this->buildServiceOverride($definition, $service, $featureStates, $portStates);

                if ($override !== []) {
                    $services[$service] = $override;
                }
            }
        }

        foreach ($dependencyOverrides as $service => $dependsOn) {
            $services[$service] ??= [];
            $services[$service]['depends_on'] = $this->tagged('!override', $dependsOn);
        }

        return $this->dumpYaml(['services' => $services]);
    }

    /**
     * @param array<string, bool> $featureStates
     * @param array<string, mixed> $portStates
     */
    public function write(string $path, array $featureStates, array $portStates = []): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create compose override directory: %s', $directory));
        }

        $content = $this->render($featureStates, $portStates);
        $tempPath = tempnam($directory, basename($path) . '.tmp-');

        if ($tempPath === false) {
            throw new RuntimeException(sprintf('Failed to create temp file for compose override: %s', $path));
        }

        try {
            if (file_put_contents($tempPath, $content) === false) {
                throw new RuntimeException(sprintf('Failed to write compose override temp file: %s', $tempPath));
            }

            if (! @rename($tempPath, $path)) {
                if (file_exists($path) && ! @unlink($path)) {
                    throw new RuntimeException(sprintf('Failed to replace compose override: %s', $path));
                }

                if (! @rename($tempPath, $path)) {
                    throw new RuntimeException(sprintf('Failed to move compose override into place: %s', $path));
                }
            }
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * @return list<string>
     */
    public function managedPortServices(): array
    {
        return array_keys(self::DEFAULT_PORT_BINDINGS);
    }

    /**
     * @param array<string, bool> $featureStates
     * @param array<string, mixed> $portStates
     * @return array<string, list<string>>
     */
    private function buildServiceOverride(
        FeatureDefinition $definition,
        string $service,
        array $featureStates,
        array $portStates,
    ): array {
        $override = [];
        $featureEnabled = $featureStates[$definition->id()] ?? $definition->enabledByDefault();

        if (! $featureEnabled) {
            $override['profiles'] = ['disabled-' . $service];
        }

        $portOverride = $this->buildPortOverride($service, $portStates[$service] ?? null);

        if ($portOverride !== null) {
            $override['ports'] = $portOverride;
        }

        return $override;
    }

    /**
     * @return list<string>|null
     */
    private function buildPortOverride(string $service, mixed $state): ?array
    {
        if (! isset(self::DEFAULT_PORT_BINDINGS[$service])) {
            return null;
        }

        if ($state === null) {
            return null;
        }

        $normalizedState = $this->normalizePortState($service, $state);

        if (! $normalizedState['enabled']) {
            return [];
        }

        if ($normalizedState['host_port'] === null) {
            return [self::DEFAULT_PORT_BINDINGS[$service]];
        }

        return [sprintf('%s:%s', $normalizedState['host_port'], self::CONTAINER_PORTS[$service])];
    }

    /**
     * @return array{enabled: bool, host_port: string|null}
     */
    private function normalizePortState(string $service, mixed $state): array
    {
        if (is_bool($state)) {
            return [
                'enabled' => $state,
                'host_port' => null,
            ];
        }

        if (is_int($state) || (is_string($state) && ctype_digit($state))) {
            return [
                'enabled' => true,
                'host_port' => (string) $state,
            ];
        }

        if (! is_array($state)) {
            throw new InvalidArgumentException(sprintf('Unsupported port state for service "%s".', $service));
        }

        $enabled = (bool) ($state['enabled'] ?? true);
        $hostPort = $state['host_port'] ?? $state['port'] ?? null;

        if ($hostPort !== null && (! is_int($hostPort) && (! is_string($hostPort) || ! ctype_digit($hostPort)))) {
            throw new InvalidArgumentException(sprintf('Invalid host port for service "%s".', $service));
        }

        return [
            'enabled' => $enabled,
            'host_port' => $hostPort === null ? null : (string) $hostPort,
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function dumpYaml(array $document): string
    {
        if ($document['services'] === []) {
            return "services: {}\n";
        }

        return $this->renderMapping($document);
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function renderMapping(array $mapping, int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);
        $lines = [];

        foreach ($mapping as $key => $value) {
            if (is_array($value)) {
                if ($this->isTaggedValue($value)) {
                    $lines = array_merge($lines, $this->renderTaggedValue($key, $value, $depth));

                    continue;
                }

                if ($value === []) {
                    $lines[] = sprintf('%s%s: []', $indent, $key);

                    continue;
                }

                if ($this->isList($value)) {
                    $lines[] = sprintf('%s%s:', $indent, $key);

                    foreach ($value as $item) {
                        if (is_array($item)) {
                            throw new RuntimeException('Nested list items are not supported in compose override rendering.');
                        }

                        $lines[] = sprintf('%s  - %s', $indent, $this->renderScalar($item));
                    }

                    continue;
                }

                $lines[] = sprintf('%s%s:', $indent, $key);
                $lines[] = rtrim($this->renderMapping($value, $depth + 1), "\n");

                continue;
            }

            $lines[] = sprintf('%s%s: %s', $indent, $key, $this->renderScalar($value));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array{__compose_tag: string, value: mixed} $value
     * @return list<string>
     */
    private function renderTaggedValue(string $key, array $value, int $depth): array
    {
        $indent = str_repeat('  ', $depth);
        $tag = $value['__compose_tag'];
        $payload = $value['value'];

        if (is_array($payload)) {
            if ($payload === []) {
                return [sprintf('%s%s: %s []', $indent, $key, $tag)];
            }

            if ($this->isList($payload)) {
                $lines = [sprintf('%s%s: %s', $indent, $key, $tag)];

                foreach ($payload as $item) {
                    $lines[] = sprintf('%s  - %s', $indent, $this->renderScalar($item));
                }

                return $lines;
            }

            $lines = [sprintf('%s%s: %s', $indent, $key, $tag)];
            $lines[] = rtrim($this->renderMapping($payload, $depth + 1), "\n");

            return $lines;
        }

        return [sprintf('%s%s: %s %s', $indent, $key, $tag, $this->renderScalar($payload))];
    }

    /**
     * @param mixed $value
     */
    private function renderScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        if ($string === '' || preg_match('/[:{}\[\],&*#?|\-<>=!%@`]/', $string) === 1 || trim($string) !== $string) {
            return '"' . addcslashes($string, "\\\"") . '"';
        }

        return $string;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param array<string, bool> $featureStates
     * @return array<string, array<string, array<string, bool|string>>>
     */
    private function buildDependencyOverrides(array $featureStates): array
    {
        $disabledServices = [];

        foreach ($this->registry->all() as $definition) {
            $featureEnabled = $featureStates[$definition->id()] ?? $definition->enabledByDefault();

            if ($featureEnabled) {
                continue;
            }

            foreach ($definition->services() as $service) {
                $disabledServices[$service] = true;
            }
        }

        if ($disabledServices === []) {
            return [];
        }

        $overrides = [];

        foreach (self::BASE_DEPENDENCIES as $service => $dependencies) {
            $filteredDependencies = array_diff_key($dependencies, $disabledServices);

            if (count($filteredDependencies) === count($dependencies)) {
                continue;
            }

            $overrides[$service] = [];

            foreach ($filteredDependencies as $dependencyService => $condition) {
                $overrides[$service][$dependencyService] = [
                    'condition' => $condition,
                    'required' => true,
                ];
            }
        }

        return $overrides;
    }

    /**
     * @return array{__compose_tag: string, value: mixed}
     */
    private function tagged(string $tag, mixed $value): array
    {
        return [
            '__compose_tag' => $tag,
            'value' => $value,
        ];
    }

    private function isTaggedValue(array $value): bool
    {
        return array_key_exists('__compose_tag', $value) && array_key_exists('value', $value);
    }
}
