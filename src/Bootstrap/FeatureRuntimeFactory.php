<?php

declare(strict_types=1);

namespace VpnBot\Bootstrap;

use Throwable;
use VpnBot\Application\Feature\ContainerManagerService;
use VpnBot\Application\Feature\ContainerRuntime;
use VpnBot\Application\Feature\DockerContainerRuntime;
use VpnBot\Application\Feature\FeatureManager;
use VpnBot\Domain\Feature\FeatureRegistry;
use VpnBot\Domain\Feature\FeatureRepository;
use VpnBot\Infrastructure\Compose\ComposeOverrideWriter;
use VpnBot\Infrastructure\Database\ConnectionFactory;
use VpnBot\Infrastructure\Database\SqliteAuditLogWriter;
use VpnBot\Infrastructure\Database\SqliteFeatureRepository;
use VpnBot\Infrastructure\Process\ProcOpenCommandRunner;

final class FeatureRuntimeFactory
{
    private ?FeatureRegistry $featureRegistry = null;
    private ?DatabaseBootstrapper $databaseBootstrapper = null;
    private ?FeatureRepository $featureRepository = null;
    private bool $featureRepositoryResolved = false;
    private ?FeatureManager $featureManager = null;
    private bool $featureManagerResolved = false;
    private ?SqliteAuditLogWriter $auditLogWriter = null;
    private bool $auditLogWriterResolved = false;
    private ?ContainerRuntime $containerRuntime = null;
    private ?ContainerManagerService $containerManagerService = null;

    public function __construct(
        private readonly string $composeOverridePath = '/docker/compose',
        private readonly string $databasePath = '/data/vpnbot.sqlite',
    ) {
    }

    public function featureRegistry(): FeatureRegistry
    {
        return $this->featureRegistry ??= new FeatureRegistry();
    }

    public function databaseBootstrapper(): DatabaseBootstrapper
    {
        return $this->databaseBootstrapper ??= new DatabaseBootstrapper(
            new ConnectionFactory(),
            $this->featureRegistry(),
            $this->databasePath,
        );
    }

    public function featureRepository(): ?FeatureRepository
    {
        if ($this->featureRepositoryResolved) {
            return $this->featureRepository;
        }

        $this->featureRepositoryResolved = true;

        try {
            return $this->featureRepository = new SqliteFeatureRepository(
                $this->databaseBootstrapper()->bootstrap(),
                $this->featureRegistry(),
            );
        } catch (Throwable) {
            return $this->featureRepository = null;
        }
    }

    public function featureManager(): ?FeatureManager
    {
        if ($this->featureManagerResolved) {
            return $this->featureManager;
        }

        $this->featureManagerResolved = true;

        try {
            $repository = $this->featureRepository();

            if ($repository === null) {
                return $this->featureManager = null;
            }

            return $this->featureManager = new FeatureManager(
                $repository,
                $this->featureRegistry(),
                new ComposeOverrideWriter($this->featureRegistry()),
                $this->containerRuntime(),
                $this->composeOverridePath,
            );
        } catch (Throwable) {
            return $this->featureManager = null;
        }
    }

    public function auditLogWriter(): ?SqliteAuditLogWriter
    {
        if ($this->auditLogWriterResolved) {
            return $this->auditLogWriter;
        }

        $this->auditLogWriterResolved = true;

        try {
            return $this->auditLogWriter = new SqliteAuditLogWriter(
                $this->databaseBootstrapper()->bootstrap(),
            );
        } catch (Throwable) {
            return $this->auditLogWriter = null;
        }
    }

    public function containerRuntime(): ContainerRuntime
    {
        return $this->containerRuntime ??= new DockerContainerRuntime(
            new ProcOpenCommandRunner(),
            [
                'docker',
                'compose',
                '-f',
                '/docker/docker-compose.yml',
                '-f',
                '/docker/compose',
            ],
        );
    }

    public function containerManagerService(): ContainerManagerService
    {
        return $this->containerManagerService ??= new ContainerManagerService(
            $this->featureRegistry(),
            $this->featureManager(),
            $this->containerRuntime(),
        );
    }

    public function bootstrapFeatureStorage(): void
    {
        $this->databaseBootstrapper()->bootstrap();
    }
}
