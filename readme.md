telegram bot to manage vpn/proxy services from Telegram.

- VLESS (Reality / Websocket / xhttp)
- NaiveProxy
- OpenConnect
- WireGuard / Amnezia
- AdGuardHome
- MTProto
- PAC / subscriptions
- automatic ssl

---

environment: ubuntu 22.04/24.04, debian 11/12

## Install

```shell
wget -O- https://raw.githubusercontent.com/mercurykd/vpnbot/master/scripts/init.sh | sh -s YOUR_TELEGRAM_BOT_KEY master
```

Runtime state now lives in SQLite at `/data/vpnbot.sqlite`. Mounted files under `/config` are generated daemon config, certificates, or compatibility inputs for explicit import only.

## Migration

1. Bootstrap or update schema:

```shell
php bin/migrate.php --db /data/vpnbot.sqlite
```

2. Import legacy state only when you explicitly migrate an old install:

```shell
php bin/import-legacy.php --db /data/vpnbot.sqlite --config-dir /config --app-config app/config.php
```

3. Verify compose rendering without starting the stack:

```shell
docker compose config
```

Do not rely on `/config/pac.json`, `/config/clients.json`, `/config/clients1.json`, or `/config/xray.stats` as runtime state after migration. Those paths are importer-only compatibility inputs, not the source of truth.
`app/config.php` remains bootstrap/admin config, while generated daemon files under `/config` stay as service outputs/inputs.

Verification policy for ongoing rewrite work:

- required safe checks: `php -l` and `docker compose config`
- optional local helpers: temporary scripts under `tmp/` only
- release confidence comes from real smoke checks on VPS/devices, not permanent repo tests

## Operations

- Restart stack: `make r`
- Webhook init: `php app/init.php`
- Cron worker: `php app/cron.php`
- PAC refresh: `php app/updatepac.php`

## Changelog

### Rewrite Milestones

- Feature toggles moved to SQLite-backed state with real Docker runtime integration.
- Xray, PAC/subscription, AdGuard, OpenConnect, NaiveProxy, Shadowsocks, Hysteria, DNSTT, MTProto, cert, maintenance, and cron flows were extracted into focused modules/actions.
- Runtime state for PAC settings, WireGuard clients, and Xray stats moved off legacy JSON state into SQLite-backed repositories.
