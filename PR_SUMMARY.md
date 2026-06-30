# PR Summary

## Why

- Replace fragile file-based runtime state with SQLite-backed state.
- Make feature toggles real: enable/disable now updates compose override and reconciles services.
- Reduce `app/bot.php` risk by extracting protocol, maintenance, and cron behavior into focused modules.

## Architecture

- Feature state is persisted in `features` and managed through `FeatureManager` + `DockerContainerRuntime`.
- Runtime settings/state moved behind repositories:
  - PAC/global settings -> `SqliteDocumentSettingsRepository`
  - WireGuard clients -> `SqliteWireGuardClientStore`
  - Xray users/stats -> `SqliteXrayStateRepository`
- Cron entrypoint now runs `CronRunner` with isolated action classes instead of one monolithic loop.
- Protocol/service concerns are split into module namespaces under `src/Module/*`.

## Migration

- `php bin/migrate.php --db /data/vpnbot.sqlite` bootstraps schema and feature defaults.
- `php bin/import-legacy.php --db /data/vpnbot.sqlite --config-dir /config --app-config app/config.php` performs explicit one-time import from legacy files.
- No automatic legacy import is performed during runtime bootstrap.

## Compatibility

- Legacy importer remains available for old installs.
- Existing entrypoints (`app/index.php`, `app/init.php`, `app/service.php`, `app/cron.php`) stay in place.
- Generated daemon config files under `/config` remain in use for service processes, but SQLite is the source of truth for runtime state.

## Test Coverage

- Feature toggles: `tests/FeatureManagerTest.php`
- DB bootstrap: `tests/DatabaseBootstrapperTest.php`
- Xray: `tests/XrayModuleTest.php`
- PAC/templates/subscriptions: `tests/PacTemplateStoreTest.php`, `tests/SubscriptionModuleTest.php`
- AdGuard/OpenConnect/NaiveProxy/Shadowsocks/Hysteria/DNSTT/MTProto/Cert/Maintenance/Cron:
  `tests/AdGuardModuleTest.php`, `tests/OpenConnectModuleTest.php`, `tests/NaiveProxyModuleTest.php`,
  `tests/ShadowsocksModuleTest.php`, `tests/HysteriaModuleTest.php`, `tests/DnsttModuleTest.php`,
  `tests/MtprotoModuleTest.php`, `tests/CertificateModuleTest.php`, `tests/MaintenanceModuleTest.php`,
  `tests/CronRunnerTest.php`
- Runtime storage migration checks:
  `tests/SqliteDocumentSettingsRepositoryTest.php`, `tests/SqliteWireGuardClientStoreTest.php`
