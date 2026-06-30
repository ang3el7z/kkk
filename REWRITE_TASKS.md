# Rewrite Tasks

Этот файл - очередь задач для ИИ-агента. Выполнять строго по порядку. Каждая задача должна оставлять проект в рабочем состоянии.

## Общие правила

- Рабочая ветка: `master`.
- Base: `upstream/dev`, commit `6b42889b2a468abe6cd13747d748acabf55d176e`.
- Не запускать весь стек: не выполнять `make u`, `make r`, `docker compose up`.
- Можно запускать статические проверки: `php -l`, temporary tmp checks, dry-run скрипты, `docker compose config` без старта контейнеров.
- Не удалять старый `Bot` до задач, где это явно указано.
- Каждый шаг должен быть маленьким: одна архитектурная цель, один понятный diff.
- Если задача требует изменить runtime behavior, сначала добавить тест/проверку или простую точку rollback.
- После каждой задачи обновлять этот файл: отметить статус и коротко что сделано.
- После успешной проверки задачи сделать отдельный commit и push.
- Один task = один commit. Не смешивать несколько задач в одном commit.
- Если проверка не прошла или есть blocker, не коммитить и не пушить.
- Перед commit выполнить `git status` и убедиться, что в commit попадают только файлы текущей задачи.
- Commit message писать в формате Conventional Commits, например `chore: add composer autoload skeleton`.
- Push делать в `origin master`, если пользователь не указал другую ветку.

## Агентский шаблон

Давать агенту задачу в таком формате:

```text
Прочитай PROJECT_MAP.md, ARCHITECTURE_PLAN.md, MIGRATION_PLAN.md, REWRITE_TASKS.md.
Выполни задачу N: <название>.
Не запускай docker compose up/make u/make r.
Сделай только описанный scope.
После успешной проверки сделай commit и push в origin master.
Если проверка не прошла, не коммить.
В конце дай: changed files, verification, commit/push status, blockers.
```

## Testing Policy

```text
Permanent tests в repo не добавлять.
Временные проверки можно писать только в tmp/.
tmp/ не stage, не commit, не push.
После задачи временные test scripts удалить или оставить ignored.
Обязательные проверки: php -l, docker compose config, real smoke checklist.
```

## Task 01 - Composer Autoload Skeleton

Status: done

Done:

- added `composer.json` with PSR-4 autoload `VpnBot\\ => src/`
- added `src/Bootstrap/Container.php` and `src/Bootstrap/Paths.php`
- entrypoints now load `vendor/autoload.php` only if it exists, so legacy startup still works without `composer install`

Цель: добавить современную загрузку классов, не меняя поведение бота.

Входные файлы:

- `app/index.php`
- `app/init.php`
- `app/service.php`
- `app/cron.php`
- `app/updatepac.php`
- `app/bot.php`

Сделать:

- Добавить `composer.json` с PSR-4 namespace `VpnBot\\` -> `src/`.
- Добавить `src/Bootstrap/Container.php` с минимальным пустым контейнером.
- Добавить `src/Bootstrap/Paths.php` для путей `/config`, `/data`, `/logs`, `/docker/compose`.
- В entrypoints подключить `vendor/autoload.php`, если файл существует.
- Не требовать `composer install` для старого запуска: если `vendor/autoload.php` нет, старый код должен работать.

Проверка:

- `php -l app/index.php`
- `php -l app/init.php`
- `php -l app/service.php`
- `php -l app/cron.php`
- `php -l app/updatepac.php`
- `php -l src/Bootstrap/Container.php`
- `php -l src/Bootstrap/Paths.php`

Не делать:

- Не переносить методы из `Bot`.
- Не менять docker compose.
- Не менять storage.

## Task 02 - SQLite Foundation

Status: done

Done:

- added `data` volume and mounted `data:/data` into `php` and `service`
- added `ConnectionFactory`, `Migrator`, initial SQLite schema, and `bin/migrate.php`
- migration CLI works with `vendor/autoload.php` when available and falls back to direct requires otherwise

Цель: подготовить SQLite как новый source of truth.

Входные файлы:

- `docker-compose.yml`
- `ARCHITECTURE_PLAN.md`
- `MIGRATION_PLAN.md`

Сделать:

- Добавить volume `data`.
- Смонтировать `data:/data` минимум в `php` и `service`.
- Добавить `src/Infrastructure/Database/ConnectionFactory.php`.
- Добавить `src/Infrastructure/Database/Migrator.php`.
- Добавить `database/migrations/001_initial.sql`.
- Добавить `bin/migrate.php`.
- Миграция должна создавать таблицы из `MIGRATION_PLAN.md`: `settings`, `admins`, `features`, `wireguard_instances`, `wireguard_clients`, `xray_users`, `xray_stats`, `openconnect_users`, `lists`, `reply_sessions`, `audit_log`.

Проверка:

- `php -l bin/migrate.php`
- `php -l src/Infrastructure/Database/ConnectionFactory.php`
- `php -l src/Infrastructure/Database/Migrator.php`
- `php bin/migrate.php --db ./tmp/vpnbot-test.sqlite`
- Проверить, что `tmp/vpnbot-test.sqlite` создан и таблицы есть.

Не делать:

- Не подключать новый DB runtime к `Bot`.
- Не удалять JSON чтение.

## Task 03 - Feature Registry

Status: done

Done:

- added `src/Domain/Feature/FeatureDefinition.php` and `src/Domain/Feature/FeatureRegistry.php`
- registered core services as non-toggleable and all protocol features as enabled by default
- added a focused local smoke check for core toggle policy, service lookup, default enablement, and menu key lookup

Цель: описать все включаемые/отключаемые возможности в одном месте.

Входные файлы:

- `docker-compose.yml`
- `PROJECT_MAP.md`
- `ARCHITECTURE_PLAN.md`

Сделать:

- Добавить `src/Domain/Feature/FeatureDefinition.php`.
- Добавить `src/Domain/Feature/FeatureRegistry.php`.
- Описать core services: `php`, `service`, `ng`, `up`; `toggleable=false`.
- Описать features:
  - `wireguard` -> `wg`
  - `wireguard_1` -> `wg1`
  - `xray` -> `xr`
  - `openconnect` -> `oc`
  - `naive` -> `np`
  - `warp` -> `wp`
  - `proxy` -> `proxy`
  - `shadowsocks` -> `ss`
  - `dnstt` -> `dnstt`
  - `hysteria` -> `hy`
  - `adguard` -> `ad`
  - `mtproto` -> `tg`
- Все feature `enabled_by_default=true`.
- Добавить метод поиска feature по service и по menu key.

Проверка:

- `php -l src/Domain/Feature/FeatureDefinition.php`
- `php -l src/Domain/Feature/FeatureRegistry.php`
- Добавить простой test script или PHPUnit test, который проверяет:
  - core нельзя toggle;
  - все services из списка найдены;
  - все features enabled by default.

Не делать:

- Не менять Telegram меню.
- Не останавливать контейнеры.

## Task 04 - Feature Repository

Status: done

Done:

- added `src/Domain/Feature/FeatureRepository.php` and `src/Infrastructure/Database/SqliteFeatureRepository.php`
- repository seeds `features` from `FeatureRegistry` defaults when table is empty
- added a focused local check for migration + seed + toggle + core-disable guard

Цель: хранить состояние feature в SQLite.

Входные файлы:

- Task 02 DB classes.
- Task 03 Feature classes.

Сделать:

- Добавить `src/Domain/Feature/FeatureRepository.php` interface.
- Добавить `src/Infrastructure/Database/SqliteFeatureRepository.php`.
- Добавить seed: если таблица `features` пустая, заполнить из `FeatureRegistry` с `enabled_by_default`.
- Добавить методы:
  - `isEnabled(string $featureId): bool`
  - `setEnabled(string $featureId, bool $enabled): void`
  - `all(): array`
- Запретить выключение core на уровне repository/service.

Проверка:

- Unit/dry-run test на temp SQLite:
  - migration;
  - seed;
  - disable `xray`;
  - попытка disable `php` дает exception.

Не делать:

- Не связывать с `Bot::menu`.

## Task 05 - Compose Manager

Status: done

Done:

- added `src/Infrastructure/Compose/ComposeOverrideWriter.php` to generate `docker-compose.override.yml` from feature state and managed port settings
- disabled features are profiled out and dependent services get `depends_on: !override` rewrites so `docker compose config` still passes
- added atomic temp-write + rename flow and focused dry-run coverage for profiles, ports, and dependency rewrites

Цель: заменить ad hoc YAML-правки на генератор compose override.

Входные файлы:

- `docker-compose.yml`
- Task 03/04 feature classes.
- текущие методы `Bot::hidePort`, `Bot::setPort`, `Bot::ports`.

Сделать:

- Добавить `src/Infrastructure/Compose/ComposeOverrideWriter.php`.
- Writer генерирует `docker-compose.override.yml` из feature state.
- Disabled feature service должен не стартовать по умолчанию. Использовать compose profiles для disabled services или другой compose-native способ, который проходит `docker compose config`.
- Сохранить текущую поддержку портов, которые меняются через настройки.
- Не редактировать YAML regex-ами.
- Добавить atomic write: write temp file -> rename.

Проверка:

- Dry-run генерации override в `tmp/docker-compose.override.yml`.
- `docker compose -f docker-compose.yml -f tmp/docker-compose.override.yml config` без запуска контейнеров.
- Проверить, что disabled `xray` скрывается из default startup semantics.

Не делать:

- Не менять `Bot::hidePort` пока; только добавить новый writer.
- Не выполнять `docker compose up`.

## Task 06 - Feature Manager

Status: done

Done:

- added `src/Application/Feature/FeatureManager.php` with enable/disable/list flows, compose override regeneration, and best-effort rollback on failure
- added `ContainerRuntime` abstraction plus `NoopContainerRuntime` for dry-run/unit-test safe runtime integration
- added focused local coverage for disable/enable `xray`, SQLite state updates, generated override changes, and recorded runtime calls

Цель: единый application service для enable/disable.

Входные файлы:

- Task 04 repository.
- Task 05 compose writer.
- Docker API wrapper in old `Bot::dockerApi`.

Сделать:

- Добавить `src/Application/Feature/FeatureManager.php`.
- Методы:
  - `enable(string $featureId): void`
  - `disable(string $featureId): void`
  - `list(): array`
- При disable:
  - update DB;
  - regenerate compose override;
  - call container stop/remove abstraction, but allow dry-run/noop adapter for tests.
- При enable:
  - update DB;
  - regenerate compose override;
  - call start abstraction, but no actual start in unit test.
- Добавить `ContainerRuntime` interface и `NoopContainerRuntime`.

Проверка:

- Unit/dry-run test: disable/enable `xray`, verify DB + generated override + recorded runtime calls.

Не делать:

- Не подключать к Telegram UI.
- Не запускать Docker реально.

## Task 06b - Real DockerContainerRuntime + DB bootstrap

Status: done

Done:

- added `DockerContainerRuntime` with process runner abstraction that issues `docker compose stop/rm/up` for affected services
- `Bot` now bootstraps `/data/vpnbot.sqlite` via migrations + feature seed defaults and wires `FeatureManager` to the real runtime
- mounted base compose file into `php`/`service` so runtime can safely recreate services from inside containers
- added narrow tests for docker runtime command generation and DB bootstrap seed behavior

Цель: заменить test-only runtime на реальный compose-backed runtime и гарантировать bootstrap SQLite без legacy auto-import.

Входные файлы:

- `app/bot.php`
- Task 02/04/06 DB + feature classes
- `docker-compose.yml`

Сделать:

- Добавить реальный `DockerContainerRuntime`, который использует Docker socket/`docker compose` через abstraction.
- `FeatureManager` в `app/bot.php` должен использовать реальный runtime, не `NoopContainerRuntime`.
- Disable feature должен stop/remove affected services.
- Enable feature должен regenerate compose override и start affected services.
- Оставить безопасный dry-run/noop mode только для tests.
- Добавить bootstrap DB/migrations: если `/data/vpnbot.sqlite` отсутствует, создать schema и seed features defaults.
- Не мигрировать legacy автоматически без явной команды.
- Добавить/обновить tests.

Проверка:

- `php -l` changed PHP
- related tests
- `php bin/migrate.php --db ./tmp/vpnbot-test.sqlite`
- `docker compose config`

## Task 07 - Menu Button Filter

Status: done

Done:

- added `src/Telegram/Menu/MenuFilter.php` to strip disabled feature buttons from inline keyboards by `callback_data`
- wired `Bot::menu()` through the new filter with guarded SQLite/repository bootstrap and allow-all fallback when DB is missing or unavailable
- added focused local coverage for `xray` and `adguard` button removal while keeping non-feature config buttons

Цель: скрывать кнопки disabled features без полного переписывания меню.

Входные файлы:

- `app/bot.php`
- Task 03/04 feature registry/repository.

Сделать:

- Добавить `src/Telegram/Menu/MenuFilter.php`.
- Filter принимает Telegram inline keyboard array и удаляет buttons по `callback_data`/menu key, если feature disabled.
- В `Bot::menu()` применить filter к main menu и protocol submenus минимально-инвазивно.
- Если DB недоступна, fallback = все enabled, чтобы старый install не ломался.

Проверка:

- `php -l app/bot.php`
- Unit/dry-run test: disabled `xray` удаляет `/xray`, disabled `adguard` удаляет `/menu adguard`.

Не делать:

- Не переписывать весь `Bot::menu`.
- Не менять тексты.

## Task 08 - Callback Guard

Status: done

Done:

- added `src/Telegram/FeatureCallbackGuard.php` to map callback/message commands to features and block disabled ones
- added an early guard in `Bot::action()` with legacy fallback on DB/repository errors and callback/message `Feature disabled` responses
- added focused local coverage for blocked `/xray` and allowed `/menu config` and `/restart`

Цель: запретить выполнение callback для disabled feature.

Входные файлы:

- `app/bot.php`
- Task 03 feature registry.

Сделать:

- Добавить `src/Telegram/FeatureCallbackGuard.php`.
- Guard определяет feature по callback_data.
- В начале `Bot::action()` проверить callback/message command.
- Если disabled:
  - answer callback: `Feature disabled`;
  - не выполнять handler.
- Fallback при DB error = старое поведение.

Проверка:

- `php -l app/bot.php`
- Unit/dry-run: `/xray` blocked when `xray=false`, `/menu config` allowed, `/restart` allowed.

Не делать:

- Не менять routing structure целиком.

## Task 09 - Container Manager Menu

Status: done

Done:

- added `src/Telegram/Menu/ContainerManagerMenuBuilder.php` for the container manager screen and toggle button rows
- added `Container manager` entry to `configMenu`, plus `/menu containers` and `/featureToggle <featureId>` handling in `Bot`
- wired toggles through `FeatureManager` with a dry-run `NoopContainerRuntime` and added focused local menu coverage

Цель: добавить UI управления контейнерами.

Входные файлы:

- `app/bot.php`
- `app/i18n.php`
- Task 06 FeatureManager.

Сделать:

- Добавить пункт в `configMenu`: `Container manager`.
- Добавить menu type/callback:
  - `/menu containers`
  - `/featureToggle <featureId>`
- Экран показывает:
  - core services locked;
  - features enabled/disabled;
  - кнопку toggle для toggleable.
- Toggle вызывает `FeatureManager`.
- После toggle обновляет это же меню.

Проверка:

- `php -l app/bot.php`
- `php -l app/i18n.php`
- Unit/dry-run для генерации menu array.

Не делать:

- Не запускать/останавливать реальные контейнеры в тестах.

## Task 10 - Legacy Import Script V1

Status: done

Done:

- added `bin/import-legacy.php` CLI with `--from`, `--db`, `--app-config`, and optional `--report`
- added `src/Infrastructure/Legacy/LegacyImporter.php` to migrate DB, seed feature defaults, and import admins/settings/WireGuard/Xray/Xray stats with missing-file tolerance
- dry-run importer now prints row counts for imported entities against repo `config/` fixtures

Цель: one-shot перенос старых данных в SQLite.

Входные файлы:

- `MIGRATION_PLAN.md`
- `config/*.json`
- `config/*.yaml`
- `app/config.php`

Сделать:

- Добавить `bin/import-legacy.php`.
- Добавить `src/Infrastructure/Legacy/LegacyImporter.php`.
- Import:
  - admins/token/debug from `app/config.php`;
  - raw `pac.json` into `settings`;
  - WG clients into `wireguard_clients`;
  - Xray users + raw config into `xray_users/settings`;
  - Xray stats into `xray_stats`;
  - feature defaults from registry.
- Missing optional files allowed.
- Import report to stdout and optional `--report=/logs/import-legacy.log`.

Проверка:

- `php -l bin/import-legacy.php`
- Dry-run against repo `config/` and temp DB.
- Confirm row counts printed.

Не делать:

- Не mutate `/config`.
- Не wire runtime to importer.

## Task 11 - Settings Repository And Pac Adapter

Status: done

Done:

- added `src/Domain/Settings/SettingsRepository.php` plus `SqliteSettingsRepository` for SQLite-backed key/value settings
- added `src/Infrastructure/Storage/LegacyPacSettingsRepository.php` as a temporary `pac.json` adapter that preserves the legacy file format
- `Bot::getPacConf()` and `Bot::setPacConf()` now proxy through the adapter, and tests cover SQLite read/write plus legacy JSON format preservation

Цель: заменить прямое чтение `/config/pac.json` в новых компонентах.

Входные файлы:

- `Bot::getPacConf`
- `Bot::setPacConf`
- Task 02 DB.

Сделать:

- Добавить `SettingsRepository` interface.
- Добавить `SqliteSettingsRepository`.
- Добавить временный adapter для старого `pac.json`, только пока `Bot` не распилен.
- Новые классы используют только repository interface.

Проверка:

- Unit/dry-run: read/write setting в SQLite.
- Legacy adapter сохраняет формат `pac.json`.

Не делать:

- Не менять все вызовы `getPacConf` сразу.

## Task 12 - Telegram Router Extraction

Status: done

Done:

- added `src/Telegram/Router.php` with initial routes for `/menu`, `/menu config`, `/menu containers`, `/featureToggle <id>`, and `/ports`
- `Bot::action()` now asks the new router first and falls back to the legacy switch when no route matches
- added focused local dry-run coverage for route matching and feature-toggle argument capture

Цель: начать вынос `Bot::action()` без большого риска.

Входные файлы:

- `app/bot.php`

Сделать:

- Добавить `src/Telegram/Router.php`.
- Добавить `Route` definitions для 5-10 простых callbacks first:
  - `/menu`
  - `/menu config`
  - `/menu containers`
  - `/featureToggle <id>`
  - `/ports`
- `Bot::action()` сначала спрашивает новый router; если route не найден, old switch работает.

Проверка:

- `php -l app/bot.php`
- Unit/dry-run route matching.

Не делать:

- Не переносить все 100+ cases за раз.

## Task 13 - WireGuard Module Extraction

Status: done

Done:

- added `src/Module/WireGuard` with `WireGuardConfigCodec`, `WireGuardModule`, `LegacyWireGuardClientStore`, and `WireGuardRuntime`
- moved WireGuard pure parse/render/name-resolution logic and legacy client file access behind the new module facade
- `Bot` now delegates `readConfig`, `readStatus`, `getName`, `saveClient`, `saveClients`, and `restartWG` through the module; added focused local codec coverage

Цель: первый большой модульный вынос на примере WireGuard.

Входные файлы:

- WireGuard methods in `app/bot.php`: `readConfig`, `readStatus`, `createConfig`, `createPeer`, `saveClient`, `saveClients`, `restartWG`, WG menus.

Сделать:

- Добавить `src/Module/WireGuard`.
- Вынести pure config parse/render first.
- Вынести client repository access.
- Оставить SSH/restart behind interface.
- `Bot` вызывает module facade.

Проверка:

- Temporary tmp checks parse/render WG config when useful.
- `php -l` changed PHP.

Не делать:

- Не менять Xray/OpenConnect in same task.

## Task 14 - Xray Module Extraction

Status: done

Done:

- added `src/Module/Xray` with DB-backed state repository, config codec/normalizer, runtime interface, and module facade
- `Bot` now delegates `getXray`, `restartXray`, `linkXray`, `getXrayStats`, and `setXrayStats` through the Xray module
- Xray config render now hydrates users from SQLite and keeps `/config/xray.json` as generated runtime config while stats move behind the module
- added focused local coverage for DB fixture render + sample config parse behavior

Цель: вынести Xray/VLESS state + config render.

Входные файлы:

- Xray methods in `app/bot.php`: `getXray`, `restartXray`, `xray`, `userXr`, `linkXray`, stats methods.

Сделать:

- Добавить `src/Module/Xray`.
- Разделить:
  - users;
  - links;
  - routing/templates;
  - stats;
  - config renderer.
- DB state -> render `/config/xray.json`.
- Runtime API/SSH behind interface.

Проверка:

- Temporary tmp checks render minimal xray config from DB fixtures when useful.
- Existing sample `/config/xray.json` can be parsed.

Не делать:

- Не переносить PAC/subscription here.

## Task 15 - Remaining Modules

Status: done

Цель: повторить pattern для остальных сервисов.

Порядок:

1. PAC/subscription
2. AdGuard
3. OpenConnect
4. NaiveProxy
5. Shadowsocks
6. Hysteria
7. DNSTT
8. MTProto
9. Cert/SSL
10. Update/backup/logs

Для каждого:

- Создать `src/Module/<Name>`.
- Вынести config parse/render.
- Вынести menu builder.
- Вынести runtime calls behind interface.
- Добавить feature guard.
- Добавить narrow tests.

Проверка:

- `php -l` changed files.
- Temporary tmp checks for module when useful.
- No full stack start.

## Task 15.1 - PAC/subscription

Status: done

Done:

- added `src/Module/Pac` with template store and subscription helper module for template selection, default handling, and Xray client template binding
- moved PAC template CRUD/default flows in `Bot` to the new module while keeping HTTP/Telegram glue in place
- `sub()`/`subscription()` now reuse extracted client lookup and template resolution instead of duplicating PAC/Xray template logic
- added focused local coverage for PAC template storage and subscription flows

## Task 15.2 - AdGuard

Status: done

Done:

- added `src/Module/AdGuard` with config repository/store, runtime interface, and module facade for password/tls sync, Xray client projection, allowed clients, and upstream updates
- `Bot` now delegates AdGuard restart/config mutations and menu data reads through the new module instead of direct YAML mutation in those paths
- added focused local coverage for AdGuard restart calls and config transforms

## Task 15.3 - OpenConnect

Status: done

Done:

- added `src/Module/OpenConnect` with text config store, passwd user loader, runtime interface, and module facade for config mutation, route rewrite, and user password management
- `Bot` now delegates OpenConnect restart/config changes, menu state parsing, and user add/delete/password flows through the new module
- added focused local coverage for OpenConnect config rewrites, route rendering, and runtime calls

## Task 15.4 - NaiveProxy

Status: done

Done:

- added `src/Module/NaiveProxy` with Caddyfile store, runtime interface, and module facade for credential parsing, basic_auth rewrites, and restart orchestration
- `Bot` now delegates NaiveProxy restart and menu credential reads through the extracted module instead of mutating `Caddyfile` inline
- added focused local coverage for NaiveProxy credential rewrites, parsing, and runtime start/stop behavior

## Task 15.5 - Shadowsocks

Status: done

Done:

- added `src/Module/Shadowsocks` with dual JSON config store, runtime interface, and module facade for password sync, v2ray-plugin toggle, and connection link rendering
- `Bot` now delegates Shadowsocks config reads, restart flows, import updates, and menu/share-link generation through the extracted module instead of mutating JSON inline
- added focused local coverage for Shadowsocks config mutation, link rendering, and runtime restart order

## Task 15.6 - Hysteria

Status: done

Done:

- added `src/Module/Hysteria` with YAML config store, runtime interface, and module facade for password sync, persisted config reads, and restart orchestration
- `Bot` now delegates Hysteria restart/import flows and menu password reads through the extracted module instead of mutating YAML inline
- added focused local coverage for Hysteria password sync, YAML persistence, and runtime start/stop behavior

## Task 15.7 - DNSTT

Status: done

Done:

- added `src/Module/Dnstt` with key-pair store, runtime interface, and module facade for restart orchestration, key import/export, and menu-state rendering
- `Bot` now delegates DNSTT key import, restart/startup flow, download path, and rendered menu data through the extracted module instead of mutating files inline
- added focused local coverage for DNSTT key persistence, restart call order, and menu-state rendering

## Task 15.8 - MTProto

Status: done

Done:

- added `src/Module/Mtproto` with file-backed config store, runtime interface, and module facade for adtag normalization, restart orchestration, link generation, and menu-state rendering
- `Bot` now delegates MTProto secret/domain/adtag persistence, restart logic, export/import state, and menu/link rendering through the extracted module instead of mutating files inline
- added focused local coverage for MTProto config persistence, adtag validation, restart command generation, and link/menu rendering

## Task 15.9 - Cert/SSL

Status: done

Done:

- added `src/Module/Cert` with certificate store, runtime interface, and module facade for letsencrypt domain collection, bundle splitting, persisted pair management, nginx cert-type parsing, and certificate inspection helpers
- `Bot` now delegates SSL import/export state, bundle persistence, letsencrypt bundle retrieval, delete flow, and nginx cert-type/expiry/domain reads through the extracted certificate module instead of mutating cert files inline
- added focused local coverage for certificate domain expansion, bundle parsing, persisted pair lifecycle, and cert-type parsing

## Task 15.10 - Update/backup/logs

Status: done

Done:

- added `src/Module/Maintenance` with log store, update-state store, runtime interface, and module facade for log file operations, branch/update state reads, and backup/autoclean schedule parsing
- `Bot` now delegates update branch state reads, reload marker persistence, log listing/clear/delete operations, and schedule formatting/normalization through the maintenance module instead of mutating update/log files inline
- added focused local coverage for maintenance log lifecycle, schedule parsing, and reload-state persistence

## Task 16 - Cron Task Extraction

Status: done

Done:

- added `src/Application/Cron` with `CronRunner`, a `CronAction` interface, and separate periodic action classes for shutdown, version checks, backups, log cleanup, xray stats reset, cert expiry, auto-analyze, and xray stats polling
- `app/cron.php` now launches `CronRunner`, while old `Bot::cron` and `check*` methods remain lightweight wrappers for compatibility
- added focused local one-tick dry-run coverage for cron without an endless loop

Цель: убрать cron logic из `Bot`.

Входные файлы:

- `app/cron.php`
- `Bot::cron`
- `checkBackup`, `checkLogs`, `checkCert`, `checkVersion`, `xrayStatsUser`, `autoAnalyzeLogs`.

Сделать:

- Добавить `src/Application/Cron/CronRunner.php`.
- Каждая periodic action = отдельный class.
- `app/cron.php` запускает `CronRunner`.
- Old `Bot::cron` остается wrapper до удаления.

Проверка:

- Unit/dry-run one tick без endless loop.
- `php -l app/cron.php`.

Не делать:

- Не менять интервалы без причины.

## Task 17 - Remove Legacy Runtime Storage

Status: done

Цель: убрать runtime-зависимость от старых JSON/PHP state.

Условие старта:

- Task 10 импорт готов.
- Основные modules читают DB.
- Config renderers пишут daemon files.

Сделать:

- Удалить прямые `file_get_contents('/config/pac.json')` как source of truth.
- Удалить прямые writes в `clients.json`, `clients1.json`, `xray.stats` как state.
- Оставить file writes только как generated daemon config.
- Обновить backup/export под DB.

Проверка:

- `rtk rg "getPacConf|setPacConf|clients\\.json|xray\\.stats" app src`
- Temporary tmp checks when useful.

Не делать:

- Не удалять legacy importer.

Done:

- `legacy.pac` runtime settings now read/write through SQLite via `SqliteDocumentSettingsRepository`; `pac.json` is no longer the live source of truth
- WireGuard client state moved to `wireguard_clients` / `wireguard_instances` through `SqliteWireGuardClientStore`; backup/export and AWG lookups now read DB state
- Xray stats read/write now stay in `xray_stats`; `xray.stats` remains referenced only by the explicit legacy importer
- Added focused SQLite-backed tests for PAC document settings, WireGuard client storage, PAC templates, subscriptions, and Xray DB state

## Task 18 - Final Cleanup And PR Prep

Status: done

Цель: подготовить код к review/upstream PR.

Сделать:

- Удалить dead code from `Bot`.
- Обновить `readme.md` install/migration section.
- Обновить `PROJECT_MAP.md`.
- Добавить changelog section.
- Запустить все статические проверки.
- Подготовить PR summary:
  - why;
  - architecture;
  - migration;
  - compatibility;
  - verification.

Проверка:

- `php -l` all changed PHP.
- Temporary tmp checks when useful.
- `docker compose config`.
- No full stack start unless user explicitly разрешит.

Done:

- removed stale Bot include usage left from the feature-toggle bootstrap path
- updated `readme.md` install/migration guidance to document SQLite runtime state, explicit legacy import, and safe verification commands
- refreshed `PROJECT_MAP.md` to reflect extracted modules, SQLite source-of-truth tables, generated daemon config files, and current safe checks
- added `PR_SUMMARY.md` with why / architecture / migration / compatibility / verification guidance for upstream review
- ran `php -l` on changed PHP, focused local checks, and `docker compose config`

## Task 19 - Testing Policy

Status: done

Цель: закрепить новый подход к проверкам: постоянные тесты больше не являются частью репозитория, основной критерий готовности - безопасные статические проверки и реальный smoke на VPS/устройствах.

Сделать:

- Добавить в `REWRITE_TASKS.md` раздел `Testing Policy` со строгими правилами:

```text
Permanent tests в repo не добавлять.
Временные проверки можно писать только в tmp/.
tmp/ не stage, не commit, не push.
После задачи временные test scripts удалить или оставить ignored.
Обязательные проверки: php -l, docker compose config, real smoke checklist.
```

- Обновить `PROJECT_MAP.md`, `PR_SUMMARY.md`, `readme.md`: убрать ожидание permanent unit tests как обязательной проверки.
- Заменить формулировки `unit tests/tests` на `temporary tmp checks` там, где речь про будущие задачи.
- Не удалять существующую папку `tests/` в этой задаче: это scope Task 20.

Проверка:

- `rtk git status`
- docs diff only
- если случайно менялся PHP: `php -l` changed PHP

Не делать:

- Не переносить и не удалять `tests/`.
- Не запускать `make u`, `make r`, `docker compose up`.

Commit:

- `docs: add temporary testing policy`

Done:

- added `Testing Policy` section with repo-level rules for temporary checks and required verification
- updated top-level verification wording to prefer `tmp/` checks over permanent repo tests
- updated `PROJECT_MAP.md`, `PR_SUMMARY.md`, and `readme.md` to align with temporary-check + real-smoke expectations

## Task 20 - Move Existing Tests To tmp

Status: done

Цель: убрать permanent tests из репозитория, но сохранить текущие test scripts локально как временный harness для ручных/агентских проверок.

Сделать:

- Создать `tmp/tests/`.
- Перенести существующие `tests/*` в `tmp/tests/*`.
- Удалить `tests/` из tracked repo.
- Добавить или проверить `.gitignore`:

```gitignore
/tmp/
```

- Убрать references на `tests/*.php` из `REWRITE_TASKS.md`, `PROJECT_MAP.md`, `PR_SUMMARY.md`, `readme.md`.
- В docs заменить обязательные проверки на:
  - `php -l`
  - `docker compose config`
  - temporary scripts in `tmp/` only when useful
  - real smoke checklist

Важно:

- `tmp/tests/*` не stage, не commit, не push.
- Commit должен содержать удаление tracked `tests/` и изменения docs/.gitignore only.

Проверка:

- `php -l` changed/all PHP
- `docker compose config`
- optional local only: `php tmp/tests/<needed>.php`, но не required и не коммитить output/scripts

Не делать:

- Не добавлять новые permanent tests.
- Не запускать `make u`, `make r`, `docker compose up`.

Commit:

- `chore: move tests to temporary checks`

Done:

- moved tracked `tests/*` files into local ignored `tmp/tests/*` for manual/agent-only validation
- removed tracked repo test files and updated docs to stop referencing deleted `tests/*.php` paths
- normalized `.gitignore` to keep `/tmp/` ignored at repo root

## Task 21 - Smoke Test Plan

Status: done

Цель: заменить ощущение готовности от agent/local checks на понятный реальный smoke checklist.

Сделать:

- Создать `SMOKE_TEST_PLAN.md`.
- Описать сценарии:
  - fresh install на VPS;
  - DB bootstrap;
  - explicit legacy import через `bin/import-legacy.php`;
  - Telegram main menu;
  - container manager;
  - disable/enable `xray`, `adguard`, `wireguard`, `mtproto`;
  - проверка, что кнопки disabled features исчезают и возвращаются;
  - проверка, что callback disabled feature blocked;
  - проверка Docker state: stopped/removed/started;
  - проверка client links/subscriptions после enable.
- Добавить expected result для каждого шага.
- Добавить fail-report format: command/action, expected, actual, logs/screenshots.

Проверка:

- docs only
- `rtk git status`
- no stack start locally

Не делать:

- Не выполнять реальный VPS smoke в этой задаче.
- Не запускать `make u`, `make r`, `docker compose up`.

Commit:

- `docs: add smoke test plan`

Done:

- added `SMOKE_TEST_PLAN.md` with VPS install, DB bootstrap, legacy import, Telegram menu, container manager, per-feature toggle, Docker state, and client-link scenarios
- documented expected results for each scenario plus a failure report template
- kept scope docs-only with no local stack start

## Task 22 - Container Runtime Hardening

Status: done

Цель: снизить риск от Docker socket и Telegram-triggered compose actions.

Сделать:

- Проверить, что toggle принимает только known `featureId` из `FeatureRegistry`.
- Проверить, что service names всегда берутся из registry/definition, не из raw callback text.
- Добавить explicit reject для unknown feature ids.
- Добавить audit log для toggle actions:
  - admin/user id;
  - feature id;
  - requested action;
  - result;
  - error text if failed.
- Добавить confirmation step before enable/disable, чтобы случайный tap не останавливал container.
- Улучшить Telegram error text при runtime failure.
- Проверить rollback DB state, если compose/runtime failed.

Проверка:

- `php -l` changed PHP
- `docker compose config`
- temporary tmp checks only if useful
- no permanent tests

Не делать:

- Не менять список features/services без причины.
- Не запускать `make u`, `make r`, `docker compose up`.

Commit:

- `feat: harden container toggles`

Done:

- added two-step container toggle confirmation with explicit `/featureToggleConfirm <feature> <enable|disable>` callbacks before runtime changes
- rejected unknown/locked toggle requests early and added safer user-facing runtime failure messages that state DB rollback behavior
- added SQLite-backed toggle audit logging for actor id, feature id, requested action, result, and error text; verified rollback with a temporary `tmp/tests` check

## Task 23 - Container Status UX

Status: done

Цель: manager должен показывать не только DB enabled/disabled, но и реальное состояние Docker containers.

Сделать:

- Добавить безопасный read-only status method в container runtime abstraction.
- Получать status только для known services из registry.
- В container manager menu показать:
  - DB state: enabled/disabled;
  - runtime state: running/stopped/missing/unknown;
  - locked state для core services.
- Добавить refresh button.
- Показать warning, если DB says enabled, но container missing/stopped.
- Fallback при Docker/status error: menu работает, status = unknown.

Проверка:

- `php -l` changed PHP
- `docker compose config`
- temporary tmp checks only if useful
- no permanent tests

Не делать:

- Не запускать/останавливать containers в этой задаче.
- Не запускать `make u`, `make r`, `docker compose up`.

Commit:

- `feat: show container runtime status`

Done:

- added read-only `status()` to the container runtime abstraction with Docker `compose ps` parsing and unknown fallback on status errors
- container manager now shows DB state, runtime state, locked core rows, drift warnings, and a refresh button without triggering any runtime changes
- verified status fallback/menu rendering with a temporary `tmp/tests` check plus `php -l` and `docker compose config`

## Task 24 - Legacy State Audit

Status: done

Цель: убедиться, что старые JSON/files не остаются runtime source-of-truth там, где state уже должен жить в SQLite.

Сделать:

- Выполнить audit:

```text
rtk rg "pac\\.json|clients\\.json|clients1\\.json|xray\\.stats|file_get_contents|file_put_contents" app src
```

- Для каждого file access разделить:
  - DB state;
  - generated daemon config;
  - app bootstrap config;
  - legacy import only.
- Убрать runtime reads/writes из `pac.json`, `clients.json`, `clients1.json`, `xray.stats`, где они ещё source-of-truth.
- Оставить generated daemon config files, если они нужны containers.
- Обновить docs: какие files generated, какие state в SQLite, какие legacy-import only.

Проверка:

- `rtk rg "pac\\.json|clients\\.json|clients1\\.json|xray\\.stats|file_get_contents|file_put_contents" app src`
- `php -l` changed/all PHP
- `docker compose config`
- temporary tmp checks only if useful

Не делать:

- Не удалять `bin/import-legacy.php`.
- Не удалять generated daemon config files.
- Не запускать `make u`, `make r`, `docker compose up`.

Commit:

- `refactor: audit legacy runtime state`

Done:

- audited legacy state references with `rtk rg`; targeted paths `pac.json`, `clients.json`, `clients1.json`, and `xray.stats` are now importer-only via `LegacyImporter`
- confirmed runtime state stays in SQLite while generated daemon config files remain under `/config` only for service processes
- updated `PROJECT_MAP.md`, `PR_SUMMARY.md`, and `readme.md` to split app bootstrap config vs importer-only legacy inputs vs generated daemon config

## Task 25 - Bot.php Slimming Phase 2

Status: done

Цель: уменьшить `app/bot.php`, оставив его facade/compat layer, а не главным местом бизнес-логики.

Сделать:

- Замерить текущий размер `app/bot.php` перед изменениями.
- Выбрать 1-2 крупных, но изолированных зоны:
  - menu builders;
  - action handlers;
  - subscription/http glue;
  - service-specific controller methods.
- Вынести выбранные зоны в `src/Telegram/*`, `src/Application/*` или `src/Module/*` по существующему pattern.
- Не менять тексты кнопок, callback_data и user-visible behavior без явной причины.
- Оставить old methods in `Bot` как wrappers, если это снижает риск.
- Обновить `PROJECT_MAP.md`.

Проверка:

- `php -l` changed/all PHP
- `docker compose config`
- temporary tmp checks only if useful
- no permanent tests

Не делать:

- Не переписывать весь `Bot` одним diff.
- Не менять runtime storage без отдельной причины.
- Не запускать `make u`, `make r`, `docker compose up`.

Commit:

- `refactor: slim bot facade`

Done:

- measured `app/bot.php` before the task at 10,232 lines; after extracting the container-manager state/toggle slice it is 10,151 lines
- extracted the container manager feature-state/runtime-state/toggle-resolution workflow into `src/Application/Feature/ContainerManagerService.php`
- kept `Bot` as a facade/wrapper for the container manager callbacks and updated `PROJECT_MAP.md` to document the new extracted service

## Task 26 - Real Device Smoke Report

Status: pending

Цель: зафиксировать результат реальной проверки на VPS и устройствах.

Сделать:

- Выполнить `SMOKE_TEST_PLAN.md` на реальном окружении.
- Создать `SMOKE_TEST_REPORT.md`.
- В отчете указать:
  - VPS OS/version;
  - install/update path;
  - DB bootstrap result;
  - legacy import result, если применимо;
  - Telegram menu result;
  - container manager result;
  - per-feature toggle result;
  - per-client/device result.
- Проверить устройства/клиенты, где доступно:
  - Android;
  - iOS;
  - Windows;
  - WireGuard/Xray/Shadowsocks/OpenConnect/other enabled protocols.
- Каждый bug оформить как отдельный next task, не чинить всё внутри smoke report.

Проверка:

- real VPS
- real Telegram bot
- real Docker container states
- real client/device connections
- no fake green without device result

Не делать:

- Не считать workflow готовым без реального smoke.
- Не коммитить secrets, tokens, IPs, private keys, screenshots with sensitive data.

Commit:

- `docs: add smoke test report`
