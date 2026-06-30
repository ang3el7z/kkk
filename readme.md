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

## Backup And Restore

Authoritative runtime state backup for a migrated install must include:

- `/data/vpnbot.sqlite`
- generated daemon config and cert material only when you also want full service-level restore: `/config/*`, `/certs/*`

Current bot-side export paths are not a full SQLite backup:

- `app/backup.php`
- bot `/export`
- pinned bot backup/export JSON produced by `Bot::export()` / `pinBackup()`

Those export flows serialize compatibility/application data as JSON for transfer/import scenarios, but they do not replace backing up `/data/vpnbot.sqlite`.

Restore guidance:

1. Restore `/data/vpnbot.sqlite` for normal post-rewrite runtime state recovery.
2. Restore generated `/config` and `/certs` artifacts when the target host should resume daemon state immediately.
3. Use `php bin/import-legacy.php --db /data/vpnbot.sqlite --config-dir /config --app-config app/config.php` only for explicit legacy migration or JSON import workflows, not as the default restore path for an already-migrated SQLite install.

Practical rule:

- migrated install restore => DB restore first
- legacy install migration => explicit importer
- bot export JSON => compatibility snapshot, not authoritative runtime backup

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
