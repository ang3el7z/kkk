# Project Map

## Current Base

- Local branch: `master`
- Upstream base: `upstream/dev`
- Base commit: `6b42889b2a468abe6cd13747d748acabf55d176e`
- Reason: upstream `master` is older (`2.29`), while `dev` contains the latest work after `2.30`. Future upstream PR should target `mercurykd/vpnbot:dev` unless upstream moves those commits to `master`.

## Entrypoints

- `app/index.php`: HTTP entrypoint for Telegram webhook, PAC/subscription routes, and webapp endpoints.
- `app/init.php`: startup initialization: webhook sync, command setup, queue cleanup, port sync.
- `app/service.php`: service-side maintenance tasks and startup helpers.
- `app/cron.php`: launches `VpnBot\Application\Cron\CronRunner`.
- `app/updatepac.php`: PAC list update worker.
- `app/backup.php`: export entrypoint.
- `app/bot.php`: still the main orchestration surface, but after Task 38 it is best understood as a legacy fallback controller containing the remaining WireGuard/import/HWID-heavy flows, menu rendering, HTTP/subscription formatting, Telegram transport helpers, runtime/config helpers, and a temporary composition root for extracted modules. See `BOT_MONOLITH_AUDIT.md`.

## Extracted Runtime Modules

- Feature toggles: `src/Application/Feature/*` including `FeatureManager`, `ContainerManagerService`, and Docker/runtime abstractions
- Cron loop/actions: `src/Application/Cron/*`
- Telegram menu builders extracted so far: `src/Telegram/Menu/ContainerManagerMenuBuilder.php`, `ConfigMenuBuilder.php`, `AdGuardMenuBuilder.php`, `OpenConnectMenuBuilder.php`, `NaiveProxyMenuBuilder.php`, `HysteriaMenuBuilder.php`
- Telegram action routing extracted so far: `src/Telegram/Router.php`, `MenuActionHandler.php`, `SettingsActionHandler.php` now cover menu/start navigation, feature toggle callbacks, and port-settings entry handlers before `Bot::action()` falls back to the legacy switch
- PAC HTTP glue extracted so far: `src/Application/Pac/PacHttpController.php` now owns `/pac*` entry routing glue, web template rendering, zapret list delivery, and subscription landing-page orchestration; `Bot::subscription()` still contains the heavy config rendering logic
- Feature/container factory wiring extracted so far: `src/Bootstrap/FeatureRuntimeFactory.php` now owns `FeatureRegistry`, DB bootstrapper, feature repository/manager, audit writer, container runtime, and container-manager service wiring; `Bot` keeps thin delegating `build*` facades
- Xray UI/action flow extracted so far: `src/Module/Xray/XrayBotFlow.php` now owns `xray()`, `userXr()`, template choice/user screens, and user add/toggle/rename/delete orchestration; `Bot` keeps thin delegates
- Post-Task-38 audit: biggest remaining `Bot` hotspots are `action()`, `subscription()`, `menu()`, `importFile()`, `statusWg()`, `hwidUser()`, and `ports()`; next extraction order is WireGuard -> import -> Telegram transport -> runtime helpers
- PAC/templates/subscriptions: `src/Module/Pac/*`
- Xray: `src/Module/Xray/*`
- AdGuard: `src/Module/AdGuard/*`
- OpenConnect: `src/Module/OpenConnect/*`
- NaiveProxy: `src/Module/NaiveProxy/*`
- Shadowsocks: `src/Module/Shadowsocks/*`
- Hysteria: `src/Module/Hysteria/*`
- DNSTT: `src/Module/Dnstt/*`
- MTProto: `src/Module/Mtproto/*`
- Certificates: `src/Module/Cert/*`
- Maintenance/update/logs: `src/Module/Maintenance/*`

## Runtime Services

- Core, not user-toggleable: `php`, `service`, `ng`, `up`
- Feature-managed services: `xr`, `oc`, `np`, `wp`, `proxy`, `ss`, `dnstt`, `hy`, `ad`, `tg`
- WireGuard daemons: `wg`, `wg1`
- Networks: `default`, `xray`
- Volumes: `adguard`, `data`, `warp`

## Runtime State Storage

- SQLite source of truth: `/data/vpnbot.sqlite`
- Feature flags: `features`
- PAC/global settings document: `settings.key = 'legacy.pac'`
- Xray template: `settings.key = 'legacy.xray_config'`
- WireGuard clients/state: `wireguard_instances`, `wireguard_clients`
- Xray users/state: `xray_users`
- Xray traffic state: `xray_stats`
- App bootstrap config: `app/config.php`
- Legacy import only: `/config/pac.json`, `/config/clients.json`, `/config/clients1.json`, `/config/xray.stats`
- Generated daemon config files: `/config/xray.json`, `/config/wg0.conf`, `/config/wg1.conf`, `/config/AdGuardHome.yaml`, `/config/hysteria.yaml`, `/config/ocserv.conf`, `/config/ocserv.passwd`, `/config/ssserver.json`, `/config/sslocal.json`, `/config/mtprotosecret`, `/certs/*`
- Audit note: the legacy JSON state paths above are no longer runtime source-of-truth; Task 24 found them only in the explicit legacy importer.

## Safe Verification Commands

- Lint targeted PHP: `php -l <file>`
- Temporary local checks only: `php tmp/<script>.php` when useful
- Schema bootstrap/migrations: `php bin/migrate.php --db ./tmp/vpnbot-test.sqlite`
- Explicit legacy import check: `php bin/import-legacy.php --db ./tmp/vpnbot-test.sqlite --config-dir ./config --app-config app/config.php`
- Compose render: `docker compose config`
- Real readiness gate: VPS/device smoke checklist

## Local Tooling Notes

- Current local PHP CLI warning `Module "pdo_sqlite" is already loaded` / `Module "sqlite3" is already loaded` is outside this repo.
- Evidence:
  - loaded CLI ini: `C:\Users\Ang3el\scoop\persist\php\cli\php.ini`
  - additional parsed ini: `C:\Users\Ang3el\scoop\apps\php\current\cli\php.ini`
  - both files currently contain `extension=pdo_sqlite` and `extension=sqlite3`
- Repo checks may print that warning locally until the user deduplicates their Scoop PHP CLI config.
- Repo code/config should not be changed to suppress that warning.

## Rewrite Docs

- Architecture: `ARCHITECTURE_PLAN.md`
- Bot monolith audit: `BOT_MONOLITH_AUDIT.md`
- Migration: `MIGRATION_PLAN.md`
- Task tracker: `REWRITE_TASKS.md`
- PR summary: `PR_SUMMARY.md`
