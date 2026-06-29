<?php
declare(strict_types=1);

namespace VpnBot\Module\Hysteria;

use RuntimeException;

final class HysteriaConfigStore
{
    public function __construct(
        private readonly string $path = '/config/hysteria.yaml',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (\function_exists('yaml_parse_file')) {
            $config = \yaml_parse_file($this->path);

            if (! is_array($config)) {
                throw new RuntimeException(sprintf('Failed to read Hysteria config: %s', $this->path));
            }

            return $config;
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read Hysteria config: %s', $this->path));
        }

        return $this->parseSimpleYaml($contents);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function save(array $config): void
    {
        if (\function_exists('yaml_emit_file')) {
            if (! \yaml_emit_file($this->path, $config)) {
                throw new RuntimeException(sprintf('Failed to write Hysteria config: %s', $this->path));
            }

            return;
        }

        if (file_put_contents($this->path, $this->dumpSimpleYaml($config)) === false) {
            throw new RuntimeException(sprintf('Failed to write Hysteria config: %s', $this->path));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSimpleYaml(string $contents): array
    {
        $result = [];
        /** @var array<int, array<string, mixed>> $stack */
        $stack = [&$result];
        $indents = [-1];

        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            if (trim($line) === '' || preg_match('/^\s*#/', $line) === 1) {
                continue;
            }

            if (preg_match('/^(\s*)([^:]+):(.*)$/', $line, $matches) !== 1) {
                throw new RuntimeException(sprintf('Unsupported Hysteria YAML line: %s', $line));
            }

            $indent = strlen($matches[1]);
            $key = trim($matches[2]);
            $rawValue = trim($matches[3]);

            while ($indent <= $indents[array_key_last($indents)] && count($stack) > 1) {
                array_pop($stack);
                array_pop($indents);
            }

            $current = &$stack[array_key_last($stack)];

            if ($rawValue === '') {
                $current[$key] = [];
                $stack[] = &$current[$key];
                $indents[] = $indent;
                unset($current);
                continue;
            }

            $current[$key] = $this->castScalar($rawValue);
            unset($current);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function dumpSimpleYaml(array $config, int $indent = 0): string
    {
        $lines = [];

        foreach ($config as $key => $value) {
            $prefix = str_repeat(' ', $indent) . $key . ':';

            if (is_array($value)) {
                $lines[] = $prefix;
                $lines[] = rtrim($this->dumpSimpleYaml($value, $indent + 4), "\n");
                continue;
            }

            $lines[] = $prefix . ' ' . $this->encodeScalar($value);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return bool|int|float|string|null
     */
    private function castScalar(string $value): mixed
    {
        $trimmed = trim($value, "\"'");

        return match (true) {
            $trimmed === 'true' => true,
            $trimmed === 'false' => false,
            $trimmed === 'null', $trimmed === '~' => null,
            is_numeric($trimmed) && str_contains($trimmed, '.') => (float) $trimmed,
            ctype_digit($trimmed) => (int) $trimmed,
            default => $trimmed,
        };
    }

    /**
     * @param bool|int|float|string|null $value
     */
    private function encodeScalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            is_int($value), is_float($value) => (string) $value,
            default => (string) $value,
        };
    }
}
