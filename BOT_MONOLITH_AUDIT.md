# Bot Monolith Audit

## Snapshot

- Audit date: 2026-06-30
- File: `app/bot.php`
- Current size: 9,838 lines, 404,175 bytes
- Shape: legacy god-object with partial migration to `src/*`; new services/modules exist, but `Bot` still owns routing glue, large menu builders, HTTP handlers, reply/session flow, transport helpers, and runtime adapters.

## Largest Methods

| Method | Lines | Notes |
| --- | ---: | --- |
| `action()` | 233-833 | Legacy regex dispatch table; biggest remaining control-flow hotspot. |
| `subscription()` | 7759-8195 | HTTP/subscription glue mixed with redirects, template selection, headers, and HWID guard flow. |
| `menu()` | 4862-5099 | Main dashboard/menu builder; pulls runtime state directly. |
| `xray()` | 6792-6985 | Large Xray menu builder with stats, templates, pagination, HWID, links. |
| `configMenu()` | 8839-9006 | Settings dashboard still assembled in `Bot`. |
| `userXr()` | 7361-7517 | Xray user details + actions + link generation. |
| `importFile()` | 1763-1912 | Large import flow still in `Bot`. |
| `statusWg()` | 3189-3335 | WireGuard status/menu rendering remains local. |

## Section Map

### 1. Action dispatch

- `action()` lines 233-833
- Status: still monolithic
- Problem: large regex switch duplicates responsibility already started in `src/Telegram/Router.php`.
- Follow-up: move remaining callback/message patterns into router maps + dedicated handlers.

### 2. Menu builders

- Main entry: `menu()` 4862-5099
- Other large menu/render zones:
  - `configMenu()` 8839-9006
  - `xray()` 6792-6985
  - `userXr()` 7361-7517
  - `adguardMenu()` 8609-8704
  - `ocMenu()` 6254-6329
  - `naiveMenu()` 6140-6176
  - `hysteriaMenu()` 6176-6204
- Status: mixed
- Already extracted:
  - container menu -> `src/Telegram/Menu/ContainerManagerMenuBuilder.php`
  - feature button filtering -> `src/Telegram/Menu/MenuFilter.php`
- Still local:
  - main/config/protocol menus
  - text formatting and inline keyboard assembly

### 3. HTTP/subscription glue

- `sub()` 7709-7758
- `subscription()` 7759-8195
- related helpers: `choiceTemplate()`, `templateUser()`, `linkXray()`, `createRuleSet()`, `createSrs()`
- Status: partially extracted
- Already extracted:
  - client lookup/origin normalization/template mutation -> `src/Module/Pac/SubscriptionModule.php`
- Still local:
  - direct `$_GET` / `$_SERVER` access
  - redirect/header/exit flow
  - response rendering via `require __DIR__ . '/subscription.php'`
  - URL construction and transport-specific delivery

### 4. Module wrappers

- Many thin wrappers now delegate into `src/Module/*`:
  - PAC/settings/template access
  - AdGuard/OpenConnect/Naive/Hysteria/DNSTT/MTProto/Cert/Maintenance/Shadowsocks
  - WireGuard/Xray state/config access
- Status: mostly facade, but not uniformly thin
- Thin wrappers safe to keep temporarily:
  - `getXrayStats()`, `setXrayStats()`
  - `getClientsOc()`
  - `expireCert()`, `domainsCert()`
  - `containerManagerMenu()`, `featureToggle()`, `featureToggleConfirm()`
- Not thin enough yet:
  - `addxrus()`, `switchXr()`, `renXrUs()`, `delxr()`
  - `adgFillAllowedClients()`
  - `changeOcExpose()`, `deloc()`
  - `choiceTemplate()`

### 5. Runtime / SSH / config helpers

- Builders/factories cluster around 9007-9773
- Low-level runtime helpers remain in `Bot`:
  - `ssh()`
  - `dockerApi()`
  - `request()`
  - `send()/update()/answer()/delete()/pin()/unpin()`
  - nginx/upstream mutators: `cloakNginx()`, `setUpstreamDomain*()`
  - port/runtime helpers: `ports()`, `hidePort()`, `setPort()`
- Status: mixed responsibilities
- Observation: extracted modules still depend on anonymous runtime adapters defined inside `Bot`, so code moved out of business logic but not out of composition root.

### 6. DB / repository / bootstrap factories

- Cluster: 9079-9773
- Extracted/new factories:
  - `buildFeatureManager()`
  - `buildDatabaseBootstrapper()`
  - `buildAuditLogWriter()`
  - `buildPacSettingsRepository()`
  - `buildSqliteSettingsRepository()`
  - module/runtime builders
- Status: acceptable as temporary composition root
- Caveat: `Bot` currently acts as both controller and service locator. Good temporary state, but factory sprawl makes the file longer and hides real controller debt.

### 7. Dead / duplicate code candidates

- Strong duplicate-risk zones:
  - `action()` vs `dispatchRouter()/buildRouter()/route*()`
  - `ports()/hidePort()/setPort()` vs `ComposeOverrideWriter`
  - `cleanDocker()` and maintenance actions vs extracted maintenance module/runtime
  - Xray/WireGuard/OpenConnect/AdGuard menu actions that still transform configs inline after module extraction
- Likely cleanup-only candidates after extraction tasks:
  - old regex routes for menus/config/containers/ports
  - direct config parsing in menu renderers once dedicated presenters/builders exist
  - anonymous runtime classes inside `build*Runtime()` methods once real runtime classes move to `src/*`
- Not safe to delete yet:
  - protocol flows still called from old dispatch paths
  - HTTP subscription glue
  - transport/path mutation helpers used by current runtime

## Task 33 Cleanup Status

- Removed: `routeConfigMenu()` from `app/bot.php`
  - Reason: no remaining callers after `Router` switched `config` callbacks onto generic `routeMenu(type)`.
- Removed: `routeContainersMenu()` from `app/bot.php`
  - Reason: no remaining callers after `Router` switched `containers` callbacks onto generic `routeMenu(type)`.
- Kept: `ports()/hidePort()/setPort()`
  - Reason: still called by `SettingsActionHandler`, callback routes, and `app/mtproto.php`.
- Kept: `cleanDocker()`
  - Reason: still called by `app/service.php`.
- Kept: feature/container `build*` wrapper methods
  - Reason: after Task 32 they are thin facades, but still active call sites inside `Bot`.

## Why `app/bot.php` Is Still Big

1. Business logic moved only partially. Modules now own storage/config primitives, but user-flow orchestration still lives in `Bot`.
2. Extraction kept backward-compatible wrappers. Good for safety, bad for file size.
3. Composition root grew inside `Bot` instead of moving to dedicated bootstrap/factory classes.
4. Large menu renderers were not extracted with protocol modules.
5. HTTP/subscription surface still mixes controller, formatter, and transport logic.

## Proposed Follow-Up Order

1. Task 29: extract menu builders/presenters from `menu()`, `configMenu()`, protocol menus.
2. Task 30: move remaining `action()` branches into router + dedicated action handlers.
3. Task 31: isolate HTTP/subscription controller from `sub()` / `subscription()`.
4. Task 32: move builder/runtime wiring from `Bot` into dedicated factory/bootstrap layer.
5. Task 33: delete stale wrappers, duplicate dispatch, and old inline config code after prior extractions land.

## Extraction Guidance

- Keep `Bot` as thin facade/controller during transition.
- Prefer one vertical slice at a time: route -> handler -> menu/presenter -> module call.
- Extract builders/presenters before cleanup deletion; otherwise diff becomes risky.
- Replace anonymous runtime classes with named classes only after handler/menu debt drops, or Task 32 will mix too much scope.

## Architecture Notes For Project Map

- `app/bot.php` is no longer just "main orchestration surface"; it is currently:
  - legacy controller/router
  - UI/menu presenter layer
  - HTTP subscription controller
  - temporary composition root for extracted modules
  - home of remaining runtime/config helper code
