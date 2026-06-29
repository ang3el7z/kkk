<?php

declare(strict_types=1);

namespace VpnBot\Module\Pac;

use RuntimeException;
use VpnBot\Domain\Settings\SettingsRepository;

final class PacTemplateStore
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly string $configDirectory = '/config',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function loadOrigin(string $type): array
    {
        $path = $this->configDirectory . '/' . $type . '.json';

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read PAC origin template: %s', $path));
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $document
     */
    public function saveOrigin(string $type, array $document): void
    {
        $path = $this->configDirectory . '/' . $type . '.json';
        $encoded = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode PAC origin template.');
        }

        if (file_put_contents($path, $encoded) === false) {
            throw new RuntimeException(sprintf('Failed to write PAC origin template: %s', $path));
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allTemplates(string $type): array
    {
        $settings = $this->settingsRepository->all();
        $templates = $settings[$type . 'templates'] ?? [];

        return is_array($templates) ? $templates : [];
    }

    /**
     * @param array<string, mixed> $template
     */
    public function saveTemplate(string $type, string $name, array $template): void
    {
        $templates = $this->allTemplates($type);
        $templates[$name] = $template;
        $this->settingsRepository->set($type . 'templates', $templates);
    }

    public function deleteTemplate(string $type, string $name): void
    {
        $templates = $this->allTemplates($type);
        unset($templates[$name]);
        $this->settingsRepository->set($type . 'templates', $templates);
    }

    public function setDefaultTemplate(string $type, ?string $encodedName): void
    {
        if ($encodedName === null || $encodedName === '') {
            $all = $this->settingsRepository->all();
            unset($all['default' . $type . 'template']);
            $this->settingsRepository->replaceAll($all);

            return;
        }

        $this->settingsRepository->set('default' . $type . 'template', $encodedName);
    }

    public function defaultTemplateToken(string $type): ?string
    {
        $value = $this->settingsRepository->get('default' . $type . 'template');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveTemplateDocument(string $type, ?string $encodedName = null): array
    {
        $templates = $this->allTemplates($type);
        $selected = $encodedName === null || $encodedName === '' ? $this->defaultTemplateToken($type) : $encodedName;

        if ($selected === null) {
            return $this->loadOrigin($type);
        }

        $decodedName = base64_decode($selected, true);

        if ($decodedName === false || $decodedName === 'origin') {
            return $this->loadOrigin($type);
        }

        return $templates[$decodedName] ?? $this->loadOrigin($type);
    }
}
