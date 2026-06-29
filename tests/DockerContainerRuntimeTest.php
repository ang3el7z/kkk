<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Application/Feature/ContainerRuntime.php';
    require dirname(__DIR__) . '/src/Application/Feature/DockerContainerRuntime.php';
    require dirname(__DIR__) . '/src/Infrastructure/Process/CommandRunner.php';
}

use VpnBot\Application\Feature\DockerContainerRuntime;
use VpnBot\Infrastructure\Process\CommandRunner;

$runner = new class () implements CommandRunner {
    /**
     * @var list<array{command:list<string>, workingDirectory:?string}>
     */
    public array $calls = [];

    public function run(array $command, ?string $workingDirectory = null): void
    {
        $this->calls[] = [
            'command' => $command,
            'workingDirectory' => $workingDirectory,
        ];
    }
};

$runtime = new DockerContainerRuntime(
    $runner,
    ['docker', 'compose', '-f', '/docker/docker-compose.yml', '-f', '/docker/compose'],
    '/app'
);

$runtime->stopAndRemove(['xr', 'ss']);
$runtime->start(['xr']);

assertDockerRuntime(
    $runner->calls === [
        [
            'command' => ['docker', 'compose', '-f', '/docker/docker-compose.yml', '-f', '/docker/compose', 'stop', 'xr', 'ss'],
            'workingDirectory' => '/app',
        ],
        [
            'command' => ['docker', 'compose', '-f', '/docker/docker-compose.yml', '-f', '/docker/compose', 'rm', '--force', '--stop', 'xr', 'ss'],
            'workingDirectory' => '/app',
        ],
        [
            'command' => ['docker', 'compose', '-f', '/docker/docker-compose.yml', '-f', '/docker/compose', 'up', '-d', '--no-deps', 'xr'],
            'workingDirectory' => '/app',
        ],
    ],
    'Docker runtime must issue compose stop/rm/up commands for affected services'
);

echo "DockerContainerRuntimeTest: OK\n";

function assertDockerRuntime(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
