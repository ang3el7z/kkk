# Project Map

## Current Base

- Local branch: `master`
- Upstream base: `upstream/dev`
- Base commit: `6b42889b2a468abe6cd13747d748acabf55d176e`
- Reason: upstream `master` is older (`2.29`), while `dev` contains the latest work after `2.30`. Future upstream PR should target `mercurykd/vpnbot:dev` unless upstream moves those commits to `master`.

## Entrypoints

- `app/index.php`: HTTP entrypoint for Telegram webhook, PAC/subscription routes, and webapp endpoints.
- `app/init.php`: container startup initialization: webhook, command setup, queue cleanup, port sync.
- `app/service.php`: service container startup tasks: self-update, config sync, docker cleanup, daemon startup helpers.
- `app/cron.php`: long-running scheduler loop.
- `app/updatepac.php`: PAC list update worker.
- `app/bot.php`: current monolith. It owns routing, auth, menus, Telegram API calls, Docker API calls, SSH calls, config reads/writes, cron jobs, and protocol logic.

## Runtime Services

- Core, not user-toggleable: `php`, `service`, `ng`, `up`.
- Protocol/service features: `wg`, `wg1`, `xr`, `oc`, `np`, `wp`, `proxy`, `ss`, `dnstt`, `hy`, `ad`, `tg`.
- Networks: `default`, `xray`.
- Volumes: `adguard`, `warp`.

## Current State Storage

- `app/config.php`: bot token, admins, debug flags.
- `/config/pac.json`: global settings, menus, lists, feature settings.
- `/config/clients.json`, `/config/clients1.json`: WireGuard client state.
- `/config/xray.json`: Xray runtime config and user state.
- `/config/xray.stats`: Xray traffic state.
- `/config/AdGuardHome.yaml`: AdGuard settings.
- `/config/hysteria.yaml`: Hysteria settings.
- `/config/ocserv.conf`, `/config/ocserv.passwd`: OpenConnect settings and users.
- Other files under `/config`, `/certs`, `/logs`, `/update` are runtime-generated or mounted config.

## Test/Run Commands

- Start stack: `make u`
- Restart stack: `make r`
- Stop stack: `make d`
- Check containers: `make ps`
- Webhook check: `make webhook`

## Rewrite Docs

- Architecture: `ARCHITECTURE_PLAN.md`
- Migration: `MIGRATION_PLAN.md`
