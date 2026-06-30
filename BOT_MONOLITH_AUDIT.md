# Bot Monolith Audit

## Snapshot

- Audit date: 2026-07-01
- File: `app/bot.php`
- Current size: 9,376 lines, 351,602 bytes
- Shape: legacy god-object with partial migration to `src/*`; after Task 41 Xray, the main WireGuard menu/status/action slice, import dispatch, and Telegram transport now live in dedicated services, while `Bot` remains a fallback router/controller, menu presenter, runtime helper host, and temporary composition root.

## Largest Methods

| Method | Lines | Notes |
| --- | ---: | --- |
| `action()` | 242-818 | Legacy regex dispatch table still exists after `guardFeatureAccess()` and `dispatchRouter()` short-circuit. |
| `subscription()` | 6479-6915 | Still owns config rendering, headers, redirect/return flow, and HWID/subscription delivery details. |
| `menu()` | 4162-4399 | Main dashboard/menu builder still reads runtime/git/backup state directly. |
| `hwidUser()` | 6283-6402 | HWID user screen remains tied to Xray user state after the main Xray flow extraction. |
| `ports()` | 8426-8483 | Legacy ports UI still parses compose/runtime state inline despite extracted settings handlers. |
| `changeTransport()` | 8820-8918 | Transport switcher still mutates protocol state inline and returns to Xray menu. |
| `xrayUpdateRules()` | 2737-2862 | Xray route/rules mutation helper is still a large inline protocol-specific mutator. |
| `pacMenu()` | 3317-3420 | PAC dashboard/menu flow still mixes runtime state reads, formatting, and inline keyboard assembly. |

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

- Main entry: `menu()` 4162-4399
- Other large menu/render zones:
  - `pacMenu()` 3317-3420
  - `hwidUser()` 6283-6402
  - `adguardMenu()` 7329-7378
  - `configMenu()` 7503-7568
  - `ocMenu()` 5504-5537
  - `naiveMenu()` 5420-5436
  - `hysteriaMenu()` 5437-5453
- Status: mixed
- Already extracted:
  - container menu -> `src/Telegram/Menu/ContainerManagerMenuBuilder.php`
  - config menu -> `src/Telegram/Menu/ConfigMenuBuilder.php`
  - AdGuard/OpenConnect/NaiveProxy/Hysteria protocol menu builders -> `src/Telegram/Menu/*MenuBuilder.php`
  - Xray dashboard/user/template/action flow -> `src/Module/Xray/XrayBotFlow.php`
  - WireGuard status/client/vless-link/default-DNS/default-MTU/subnet/AllowedIPs flow -> `src/Module/WireGuard/WireGuardBotFlow.php`
  - feature button filtering -> `src/Telegram/Menu/MenuFilter.php`
- Still local:
  - main dashboard
  - PAC menu flows
  - Xray-adjacent HWID screens and transport toggles
  - text formatting and inline keyboard assembly

### 3. HTTP/subscription glue

- `sub()` 6629-6633
- `subscription()` 6634-7070
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
  - import prompt/import-file dispatch via `src/Application/Import/ImportFlow.php`
- Status: mostly facade, but not uniformly thin
- Thin wrappers safe to keep temporarily:
  - `getXrayStats()`, `setXrayStats()`
  - `getClientsOc()`
  - `expireCert()`, `domainsCert()`
  - `containerManagerMenu()`, `featureToggle()`, `featureToggleConfirm()`
- Not thin enough yet:
  - `hwidUser()` and related HWID user screens
  - `adgFillAllowedClients()`
  - `changeOcExpose()`, `deloc()`
  - `changeTransport()`
  - `xrayUpdateRules()` and remaining route/tun mutators

### 5. Runtime / SSH / config helpers

- Builders/factories cluster around 8769-9461
- Low-level runtime helpers remain in `Bot`:
  - `ssh()`
  - `dockerApi()`
  - nginx/upstream mutators: `cloakNginx()`, `setUpstreamDomain*()`
  - port/runtime helpers: `ports()`, `hidePort()`, `setPort()`
- Status: mixed responsibilities
- Transport note: `src/Telegram/TelegramClient.php` now owns Telegram HTTP API glue; `Bot` keeps thin delegating wrappers for compatibility.
- Observation: extracted modules still depend on anonymous runtime adapters defined inside `build*Runtime()` methods, so business logic moved out faster than runtime composition.

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
  - Xray/OpenConnect/AdGuard menu actions that still transform configs inline after module extraction
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

## Post-Task-40 State

- Tasks 29-40 are reflected in the current file shape:
  - menu builders extracted for config/AdGuard/OpenConnect/NaiveProxy/Hysteria/container manager
  - router/action handlers extracted for menu/start/container/settings slice
  - PAC HTTP controller extracted
  - feature/runtime bootstrap extracted into `FeatureRuntimeFactory`
  - duplicate menu-route wrappers removed
  - local PHP CLI SQLite warning documented outside repo code changes
  - Xray dashboard/user/template/action flow extracted into `src/Module/Xray/XrayBotFlow.php`
  - WireGuard status/client/vless-link/default-DNS/default-MTU/subnet/AllowedIPs flow extracted into `src/Module/WireGuard/WireGuardBotFlow.php`
  - import prompt/payload loading/protocol dispatch/finalization extracted into `src/Application/Import/ImportFlow.php`
- Result: biggest remaining business hotspots are now subscription rendering, HWID screens, and low-level runtime helpers.

## Why `app/bot.php` Is Still Big

1. Business logic moved only partially. Modules now own storage/config primitives, but some Xray-adjacent HWID/transport screens still live in `Bot`.
2. Extraction kept backward-compatible wrappers. Good for safety, bad for file size.
3. SSH/runtime helpers still live beside controller logic.
4. Composition root is thinner than before, but anonymous runtime adapters still keep many lines in `Bot`.
5. HTTP/subscription rendering still mixes controller, formatter, and delivery logic.

## Proposed Follow-Up Order

1. Task 42: extract runtime helpers and anonymous adapters. This is now the clearest remaining infrastructure-heavy slice.
2. After 42, run a new dead-code cleanup pass before broader audits.

## Extraction Guidance

- Keep `Bot` as thin facade/controller during transition.
- Prefer one vertical slice at a time: route -> handler -> menu/presenter -> module call.
- Shrink `action()` only by removing whole protocol slices, not by reshuffling regex cases in place.
- Replace anonymous runtime classes with named classes only after handler/menu debt drops, or Task 42 will mix too much scope.

## Architecture Notes For Project Map

- `app/bot.php` is no longer just "main orchestration surface"; it is currently:
  - legacy fallback controller/router
  - UI/menu presenter layer for the remaining PAC/HWID-heavy flows
  - HTTP/subscription formatter/delivery layer
  - temporary composition root for extracted modules
  - home of remaining runtime/config helper code
