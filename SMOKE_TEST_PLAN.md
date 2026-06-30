# Smoke Test Plan

## Scope

This checklist is the real readiness gate after safe local checks (`php -l`, `docker compose config`, optional `tmp/` helpers). Do not mark the rewrite complete until the scenarios below pass on a real VPS and real client devices.

## Environment Capture

Before starting, record:

- VPS provider or host label
- OS and version
- install path (`fresh install` or `update existing install`)
- branch/commit under test
- Telegram bot username used for smoke

## Scenario 1 - Fresh Install On VPS

Action:

1. Provision a clean VPS that matches supported distro requirements.
2. Run the normal install path for the target branch/version.
3. Wait for install to finish without manually patching files.

Expected:

- install finishes without fatal shell or Docker errors
- required containers/services are created
- bot webhook/init path is reachable
- no manual recovery steps are required

## Scenario 2 - DB Bootstrap

Action:

1. Inspect `/data/vpnbot.sqlite` after install/startup bootstrap.
2. Confirm schema exists and default feature rows were created.

Expected:

- `/data/vpnbot.sqlite` exists
- schema tables are present
- default feature state is populated
- runtime works without requiring legacy import

## Scenario 3 - Explicit Legacy Import

Action:

1. Start from a legacy config set or backup.
2. Run `php bin/import-legacy.php --db /data/vpnbot.sqlite --config-dir /config --app-config app/config.php`.
3. Re-open bot flows that depend on imported state.

Expected:

- import exits successfully
- expected admins/settings/clients are present after import
- no legacy files are mutated unexpectedly
- runtime uses imported SQLite state afterward

## Scenario 4 - Telegram Main Menu

Action:

1. Open the bot as an admin user.
2. Render the main menu and core protocol/service submenus.

Expected:

- main menu opens without errors
- expected buttons are present for enabled features
- menu text/callbacks remain usable
- no disabled-only buttons leak into the menu

## Scenario 5 - Container Manager

Action:

1. Open `Settings -> Container manager`.
2. Inspect core and toggleable feature rows.

Expected:

- core services show as locked
- toggleable features show current state clearly
- menu redraw is stable across repeated opens
- no unknown feature ids appear

## Scenario 6 - Disable/Enable Xray

Action:

1. Disable `xray` from the container manager.
2. Re-open menus and trigger an Xray callback/action.
3. Re-enable `xray`.
4. Re-open menus and generate an Xray client link/subscription again.

Expected:

- disable stops/removes the expected runtime service(s)
- Xray buttons disappear while disabled
- Xray callback/action is blocked with disabled feedback
- enable restores buttons and runtime service(s)
- Xray client link/subscription works again after enable

## Scenario 7 - Disable/Enable AdGuard

Action:

1. Disable `adguard`.
2. Check menu visibility and runtime state.
3. Re-enable `adguard`.

Expected:

- AdGuard buttons disappear while disabled
- runtime service stops/removes on disable
- buttons return on enable
- runtime service returns on enable

## Scenario 8 - Disable/Enable WireGuard

Action:

1. Disable `wireguard`.
2. Check menu visibility and WireGuard-related actions.
3. Re-enable `wireguard`.
4. Generate or inspect client config/link after enable.

Expected:

- WireGuard buttons disappear while disabled
- disabled callbacks/actions are blocked
- runtime service stops/removes on disable
- runtime service returns on enable
- client config/link flow works again after enable

## Scenario 9 - Disable/Enable MTProto

Action:

1. Disable `mtproto`.
2. Check menu visibility and MTProto export/share flow.
3. Re-enable `mtproto`.

Expected:

- MTProto buttons disappear while disabled
- runtime service stops/removes on disable
- disabled callbacks/actions are blocked
- export/share flow works again after enable

## Scenario 10 - Docker State Reconciliation

Action:

1. During each toggle scenario, inspect Docker state directly.
2. Compare DB state, visible menu state, and container state.

Expected:

- disable path results in expected stopped/removed state
- enable path results in expected started/running state
- no unrelated core services are stopped
- DB state and runtime state converge after each toggle

## Scenario 11 - Client Links And Subscriptions After Re-Enable

Action:

1. After each feature re-enable, request the relevant client output again.
2. Test links/subscriptions on at least one real client per supported protocol that was toggled.

Expected:

- generated links/subscriptions are non-empty and well-formed
- client imports succeed on target devices
- connection works after re-enable

## Device Matrix

Record results when available for:

- Android
- iOS
- Windows
- WireGuard
- Xray/VLESS
- Shadowsocks
- OpenConnect
- any other enabled protocol under test

## Failure Report Format

For each failure, record:

- command/action
- expected
- actual
- logs
- screenshots
- affected feature/protocol
- environment details

## Exit Criteria

Smoke passes only when:

- all required scenarios were executed
- expected results matched actual behavior
- failures, if any, are written down as follow-up tasks
- no secret/token/private-key data is captured in logs or screenshots
