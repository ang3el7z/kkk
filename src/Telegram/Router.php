<?php

declare(strict_types=1);

namespace VpnBot\Telegram;

final class Router
{
    /**
     * @var list<array{pattern: string, handler: string, source: string}>
     */
    private array $routes;

    public function __construct()
    {
        $this->routes = [
            ['pattern' => '~^/menu$~', 'handler' => 'routeMenu', 'source' => 'message'],
            ['pattern' => '~^/menu$~', 'handler' => 'routeMenu', 'source' => 'callback'],
            ['pattern' => '~^/menu config$~', 'handler' => 'routeConfigMenu', 'source' => 'callback'],
            ['pattern' => '~^/menu containers$~', 'handler' => 'routeContainersMenu', 'source' => 'callback'],
            ['pattern' => '~^/featureToggle (?P<feature>[a-z0-9_]+)$~', 'handler' => 'routeFeatureToggle', 'source' => 'callback'],
            ['pattern' => '~^/ports$~', 'handler' => 'routePorts', 'source' => 'callback'],
        ];
    }

    /**
     * @param array{message?: string, callback?: string} $input
     * @return array{handler: string, args: array<int, string>}|null
     */
    public function match(array $input): ?array
    {
        foreach ($this->routes as $route) {
            $payload = $input[$route['source']] ?? '';

            if (! is_string($payload) || preg_match($route['pattern'], $payload, $matches) !== 1) {
                continue;
            }

            $args = [];
            $namedArgs = [];

            foreach ($matches as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $namedArgs[] = (string) $value;
            }

            if ($namedArgs !== []) {
                $args = $namedArgs;
            } else {
                foreach ($matches as $key => $value) {
                    if (! is_int($key) || $key === 0) {
                        continue;
                    }

                    $args[] = (string) $value;
                }
            }

            return [
                'handler' => $route['handler'],
                'args' => $args,
            ];
        }

        return null;
    }
}
