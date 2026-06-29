<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Telegram/Router.php';
}

use VpnBot\Telegram\Router;

$router = new Router();

$menuRoute = $router->match(['message' => '/menu']);
$configRoute = $router->match(['callback' => '/menu config']);
$containersRoute = $router->match(['callback' => '/menu containers']);
$toggleRoute = $router->match(['callback' => '/featureToggle xray']);
$portsRoute = $router->match(['callback' => '/ports']);
$missRoute = $router->match(['callback' => '/xray']);

assertRouter($menuRoute !== null && $menuRoute['handler'] === 'routeMenu', '/menu message must match routeMenu');
assertRouter($configRoute !== null && $configRoute['handler'] === 'routeConfigMenu', '/menu config must match routeConfigMenu');
assertRouter($containersRoute !== null && $containersRoute['handler'] === 'routeContainersMenu', '/menu containers must match routeContainersMenu');
assertRouter($toggleRoute !== null && $toggleRoute['handler'] === 'routeFeatureToggle' && $toggleRoute['args'] === ['xray'], '/featureToggle must capture feature id');
assertRouter($portsRoute !== null && $portsRoute['handler'] === 'routePorts', '/ports must match routePorts');
assertRouter($missRoute === null, 'unregistered callbacks must fall through to old switch');

echo "RouterTest: OK\n";

function assertRouter(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
