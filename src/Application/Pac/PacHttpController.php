<?php

declare(strict_types=1);

namespace VpnBot\Application\Pac;

final class PacHttpController
{
    public function __construct(
        private readonly object $bot,
        private readonly string $subscriptionTemplatePath,
    ) {
    }

    public function handleSubscriptionLanding(): void
    {
        $xr     = $this->bot->getXray();
        $pac    = $this->bot->getPacConf();
        $st     = $this->bot->getXrayStats();
        $domain = $_GET['cdn'] ?: ($_SERVER['SERVER_NAME'] ?: $this->bot->getDomain($pac['transport'] != 'Reality'));
        $scheme = empty($this->bot->nginxGetTypeCert()) ? 'http' : 'https';
        $hash   = $this->bot->getHashBot();
        $match  = $this->bot->buildSubscriptionModule()->findClientByUuid($xr, (string) $_GET['id']);
        $k      = $match['index'];
        $client = $match['client'];
        $flag   = ! empty($client['off']);
        $uid    = $client['id'];
        $email  = $client['email'];
        $expire = $client['time'];

        if (! $flag && ! $this->bot->processHwidRequest($client)) {
            exit;
        }

        $suburl = "<a href='$scheme://{$domain}/pac$hash/sub?id={$uid}'>subscription</a>";
        $download = $this->bot->getBytes($st['users'][$k]['global']['download'] + $st['users'][$k]['session']['download']);
        $upload = $this->bot->getBytes($st['users'][$k]['global']['upload'] + $st['users'][$k]['session']['upload']);
        $singbox = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 'si',
            's' => $uid,
        ]));
        $xray = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 's',
            's' => $uid,
        ]));
        $clash = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 'cl',
            's' => $uid,
        ]));
        $vless = $this->bot->linkXray($k);
        $windows = "$scheme://{$domain}/pac$hash?t=si&r=w&s=$uid";

        $_GET['s'] = $uid;

        foreach ([
            'xray' => 's',
            'singbox' => 'si',
            'clash' => 'cl',
        ] as $name => $type) {
            $_GET['t'] = $type;
            $configs[$name] = $this->bot->subscription(1);
        }

        require $this->subscriptionTemplatePath;
    }

    public function handlePacRequest(string $hash, bool $webapp): void
    {
        if (! empty($t = unserialize(base64_decode(explode('/', $_SERVER['REQUEST_URI'])[2])))) {
            $_GET = array_merge($_GET, $t);
        }

        $type = $_GET['t'] ?? 'pac';
        $address = $_GET['a'] ?: '127.0.0.1';
        $port = $_GET['p'] ?: '1080';

        switch ($type) {
            case 's':
            case 'si':
            case 'cl':
                $this->bot->subscription();
                exit;

            case 'te':
                if (! empty($_GET['te'])) {
                    $t = $this->bot->getPacConf()["{$_GET['ty']}templates"][$_GET['te']];
                } else {
                    $t = json_decode(file_get_contents("/config/{$_GET['ty']}.json"), true);
                }

                if ($t) {
                    header('Content-Type: text/html');
                    $t = json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $name = $_GET['te'] ?: 'origin';
                    $type = $_GET['ty'];
                    echo <<<HTML
                        <!DOCTYPE HTML>
                        <html lang="en" style="height:100%">
                        <head>
                            <meta charset="utf-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">

                            <link href="/webapp$hash/jsoneditor.min.css" rel="stylesheet" type="text/css">
                            <script src="/webapp$hash/jsoneditor.min.js"></script>
                            <script src="/webapp$hash/jquery-3.7.1.min.js"></script>
                            <script src="https://telegram.org/js/telegram-web-app.js"></script>
                        </head>
                        <body style="height:100%">
                            <div id="jsoneditor" style="height:100%"></div>

                            <script>
                                jQuery(function($) {
                                    var tg = window.Telegram.WebApp;
                                    const container = document.getElementById("jsoneditor")
                                    const options = {}
                                    const editor = new JSONEditor(container, options)
                                    editor.set({$t})
                                    tg.MainButton.show().setText('{$this->bot->i18n('save')}').onClick(function (e) {
                                        var self = this;
                                        $.ajax({
                                            url: '/webapp$hash/save?' + tg.initData,
                                            method: 'POST',
                                            data: {
                                                name: '$name',
                                                type: '$type',
                                                json: editor.getText()
                                            },
                                            dataType: 'json'
                                        }).done(function (r) {
                                            if (r.status == true) {
                                                tg.MainButton.setText('{$this->bot->i18n('success')}')
                                                setTimeout(() => {
                                                    tg.close();
                                                }, 500);
                                            } else {
                                                tg.MainButton.setText(r.message);
                                            }
                                        }).fail(function (r) {
                                            tg.MainButton.setText('{$this->bot->i18n('error')}')
                                        });
                                    });
                                });
                            </script>
                        </body>
                        </html>
                        HTML;
                    exit;
                }
                break;

            default:
                if (file_exists($file = __DIR__ . "/../../../app/zapretlists/$type")) {
                    $pac = file_get_contents($file);
                    header('Content-Type: text/plain');
                    echo str_replace([
                        '~address~',
                        '~port~',
                    ], [
                        $address,
                        $port,
                    ], $pac);
                    exit;
                }
                break;
        }
    }
}
