<?php
declare(strict_types=1);

namespace VpnBot\Module\Maintenance;

use RuntimeException;

final class UpdateStateStore
{
    public function __construct(
        private readonly string $updateDir = '/update',
    ) {
    }

    public function trackedBranch(): string
    {
        return trim($this->read('branch', true));
    }

    public function reloadMessage(): string
    {
        return trim($this->read('reload_message', true));
    }

    public function message(): string
    {
        return $this->read('message', true);
    }

    public function updateJsonPath(): string
    {
        return $this->updateDir . '/json';
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function writeReloadArtifacts(string $reloadMessage, string $key, array $payload, string $pipe): void
    {
        $this->write('reload_message', $reloadMessage);
        $this->write('key', $key);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode update payload.');
        }

        $this->write('curl', $json);
        $this->write('pipe', $pipe);
    }

    public function clearReloadArtifacts(): void
    {
        $this->write('message', '');
        $this->write('reload_message', '');
    }

    public function writeMessage(string $message): void
    {
        $this->write('message', $message);
    }

    private function read(string $name, bool $optional): string
    {
        $path = $this->updateDir . '/' . $name;

        if ($optional && ! file_exists($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            if ($optional) {
                return '';
            }

            throw new RuntimeException(sprintf('Failed to read update state: %s', $path));
        }

        return $contents;
    }

    private function write(string $name, string $contents): void
    {
        $path = $this->updateDir . '/' . $name;

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Failed to write update state: %s', $path));
        }
    }
}
