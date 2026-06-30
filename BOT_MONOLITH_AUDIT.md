# Bot Monolith Audit

## Snapshot

- Audit date: 2026-07-01
- File: `app/bot.php`
- Current size: 10,620 lines, 404,175 bytes
- Shape: legacy god-object with partial migration to `src/*`; after Tasks 29-34 the file now acts mainly as fallback router/controller, menu presenter, transport client, runtime helper host, and temporary composition root for extracted modules.

## Largest Methods

| Method | Lines | Notes |
| --- | ---: | --- |
| `action()` | 242-818 | Legacy regex dispatch table still exists after `guardFeatureAccess()` and `dispatchRouter()` short-circuit. |
| `subscription()` | 7607-8043 | Still owns config rendering, headers, redirect/return flow, and HWID/subscription delivery details. |
| `menu()` | 4847-5084 | Main dashboard/menu builder still reads runtime/git/backup state directly. |
| `xray()` | 6685-6878 | Xray dashboard/menu builder with stats, transport, pagination, and action buttons. |
| `userXr()` | 7254-7410 | Xray user details, HWID controls, and link output still assembled inline. |
| `importFile()` | 1748-1897 | Import dispatch still mixes parsing, protocol detection, and user messaging. |
| `statusWg()` | 3174-3320 | WireGuard status/menu rendering remains local. |
| `ports()` | 9533-9590 | Legacy ports UI still parses compose/runtime state inline despite extracted settings handlers. |

## Section Map

### 1. Action dispatch

- `action()` lines 233-833
- `action()` lines 242-818
- Status: still monolithic, but now front-loaded with `guardFeatureAccess()` and `dispatchRouter()`
- Already extracted around it:
  - `src/Telegram/Router.php`
  - `src/Telegram/MenuActionHandler.php`
  - `src/Telegram/SettingsActionHandler.php`
- Problem: large regex switch still owns protocol-heavy branches after router fallback.
- Follow-up: keep shrinking legacy cases until `action()` becomes a thin fallback or disappears.

### 2. Menu builders

- Main entry: `menu()` 4847-5084
- Other large menu/render zones:
  - `xray()` 6685-6878
  - `userXr()` 7254-7410
  - `statusWg()` 3174-3320
  - `pacMenu()` 4002-4105
  - `adguardMenu()` 8447-8496
  - `configMenu()` 8631-8696
  - `ocMenu()` 6189-6222
  - `naiveMenu()` 6105-6121
  - `hysteriaMenu()` 6122-6138
- Status: mixed
- Already extracted:
  - container menu -> `src/Telegram/Menu/ContainerManagerMenuBuilder.php`
  - config menu -> `src/Telegram/Menu/ConfigMenuBuilder.php`
  - AdGuard/OpenConnect/NaiveProxy/Hysteria protocol menu builders -> `src/Telegram/Menu/*MenuBuilder.php`
  - feature button filtering -> `src/Telegram/Menu/MenuFilter.php`
- Still local:
  - main dashboard
  - Xray/WireGuard/PAC menu flows
  - text formatting and inline keyboard assembly

### 3. HTTP/subscription glue

- `sub()` 7602-7606
- `subscription()` 7607-8043
- related helpers: `choiceTemplate()`, `templateUser()`, `linkXray()`, `createRuleSet()`, `createSrs()`
- Status: partially extracted
- Already extracted:
  - `/pac*` entry routing glue, landing-page orchestration, and web template rendering -> `src/Application/Pac/PacHttpController.php`
  - client lookup/origin normalization/template mutation -> `src/Module/Pac/SubscriptionModule.php`
- Still local:
  - direct `$_GET` / `$_SERVER` access
  - final config rendering for subscription formats
  - some redirect/header/return flow
  - URL construction and transport-specific delivery details

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
  - `statusWg()` and related WG menu/status helpers
  - `adgFillAllowedClients()`
  - `changeOcExpose()`, `deloc()`
  - `importFile()`
  - `choiceTemplate()`

### 5. Runtime / SSH / config helpers

- Builders/factories cluster around 8769-9461
- Low-level runtime helpers remain in `Bot`:
  - `ssh()`
  - `dockerApi()`
  - `request()`
  - `send()/update()/answer()/delete()/pin()/unpin()`
  - nginx/upstream mutators: `cloakNginx()`, `setUpstreamDomain*()`
  - port/runtime helpers: `ports()`, `hidePort()`, `setPort()`
- Status: mixed responsibilities
- Observation: extracted modules still depend on anonymous runtime adapters defined inside `build*Runtime()` methods, so business logic moved out faster than transport/runtime composition.

### 6. DB / repository / bootstrap factories

- Cluster: 8769-9461
- Extracted/new factories:
  - `buildFeatureManager()`
  - `buildDatabaseBootstrapper()`
  - `buildAuditLogWriter()`
  - `buildPacSettingsRepository()`
  - `buildSqliteSettingsRepository()`
  - `buildFeatureRuntimeFactory()`
  - module/runtime builders
- Status: acceptable as temporary composition root
- Caveat: `Bot` currently acts as both controller and service locator. Good temporary state, but factory sprawl makes the file longer and hides real controller debt.

### 7. Dead / duplicate code candidates

- Strong duplicate-risk zones:
  - `action()` vs `dispatchRouter()/buildRouter()/route*()`
  - `ports()/hidePort()/setPort()` vs `ComposeOverrideWriter`
  - Telegram transport methods vs future dedicated `TelegramClient`
  - `cleanDocker()` and maintenance actions vs extracted maintenance module/runtime
  - Xray/WireGuard/OpenConnect/AdGuard menu actions that still transform configs inline after module extraction
- Likely cleanup-only candidates after extraction tasks:
  - residual old regex routes once Xray/WG/import/transport slices leave `action()`
  - direct config parsing in menu renderers once Xray/WG/PAC presenters exist
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

## Post-Task-34 State

- Tasks 29-34 are all reflected in the current file shape:
  - menu builders extracted for config/AdGuard/OpenConnect/NaiveProxy/Hysteria/container manager
  - router/action handlers extracted for menu/start/container/settings slice
  - PAC HTTP controller extracted
  - feature/runtime bootstrap extracted into `FeatureRuntimeFactory`
  - duplicate menu-route wrappers removed
  - local PHP CLI SQLite warning documented outside repo code changes
- Result: biggest remaining business hotspots are now Xray, WireGuard, import flow, Telegram transport, and low-level runtime helpers.

## Why `app/bot.php` Is Still Big

1. Business logic moved only partially. Modules now own storage/config primitives, but user-flow orchestration for Xray/WireGuard/import still lives in `Bot`.
2. Extraction kept backward-compatible wrappers. Good for safety, bad for file size.
3. Telegram transport and SSH/runtime helpers still live beside controller logic.
4. Composition root is thinner than before, but anonymous runtime adapters still keep many lines in `Bot`.
5. HTTP/subscription rendering still mixes controller, formatter, and delivery logic.

## Proposed Follow-Up Order

1. Task 38: extract Xray user/menu/action flow. It owns the largest remaining protocol slice and will shrink both `action()` and menu/render debt.
2. Task 39: extract WireGuard status/menu/action flow. It is the next large protocol-specific UI hotspot.
3. Task 40: extract import flow. This removes mixed parsing + Telegram response logic from the monolith.
4. Task 41: extract Telegram transport into a dedicated client. This separates controller logic from HTTP API plumbing.
5. Task 42: extract runtime helpers and anonymous adapters. Do this after higher-level flow extraction so runtime refactor stays narrow.
6. After 38-42, run a new dead-code cleanup pass before broader audits.

## Extraction Guidance

- Keep `Bot` as thin facade/controller during transition.
- Prefer one vertical slice at a time: route -> handler -> menu/presenter -> module call.
- Shrink `action()` only by removing whole protocol slices, not by reshuffling regex cases in place.
- Replace anonymous runtime classes with named classes only after handler/menu debt drops, or Task 42 will mix too much scope.

## Architecture Notes For Project Map

- `app/bot.php` is no longer just "main orchestration surface"; it is currently:
  - legacy fallback controller/router
  - UI/menu presenter layer for the biggest remaining protocol flows
  - HTTP/subscription formatter/delivery layer
  - temporary composition root for extracted modules
  - home of remaining Telegram transport and runtime/config helper code
