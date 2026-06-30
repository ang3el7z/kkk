<?php

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

require __DIR__ . '/timezone.php';
require __DIR__ . '/config.php';
if ($c['debug']) {
    require __DIR__ . '/debug.php';
}
require __DIR__ . '/calc.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/i18n.php';
if (file_exists(__DIR__ . '/override.php')) {
    include __DIR__ . '/override.php';
}
$bot  = new Bot($c['key'], $i);
$hash = $bot->getHashBot();
if (!empty($_GET['hash'])) {
    $t = $_GET;
    unset($t['hash']);
    ksort($t);
    foreach ($t as $k => $v) {
        $s[] = "$k=$v";
    }
    $s      = implode("\n", $s);
    $sk     = hash_hmac('sha256', $c['key'], "WebAppData", true);
    $webapp = hash_hmac('sha256', $s, $sk) == $_GET['hash'];
}

switch (true) {
    // tlgrm
    case 'POST' == $_SERVER['REQUEST_METHOD'] && preg_match('~^/tlgrm~', $_SERVER['REQUEST_URI']) && $_GET['k'] == $c['key']:
        $bot->input();
        break;

    // save template
    case preg_match('~^' . preg_quote("/webapp$hash/save") . '~', $_SERVER['REQUEST_URI']) && $webapp && !empty($_POST['json']):
        echo json_encode($bot->saveTemplate($_POST['name'], $_POST['type'], $_POST['json']));
        break;

    // adguard cookie
    case preg_match('~^' . preg_quote("/webapp$hash/check") . '~', $_SERVER['REQUEST_URI']) && $webapp:
        setcookie('c', $hash, 0, '/');
        echo "/adguard$hash/";
        break;

    case preg_match('~^' . preg_quote("/pac$hash/sub") . '~', $_SERVER['REQUEST_URI']) && file_exists(__DIR__ . '/subscription.php'):
        $bot->buildPacHttpController()->handleSubscriptionLanding();
        exit;

    // subs & pac
    case preg_match('~^' . preg_quote("/pac$hash") . '~', $_SERVER['REQUEST_URI']):
        $bot->buildPacHttpController()->handlePacRequest($hash, $webapp);
        break;

    default:
        header('500', true, 500);
}
