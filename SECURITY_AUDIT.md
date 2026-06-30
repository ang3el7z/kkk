# Security Audit

Date: 2026-07-01

Scope: Task 45 `Security And Admin Audit`

## Summary

- Telegram command execution is admin-gated at the top level: `Bot::input()` only runs `session()` and `action()` after `auth()` marks the sender as admin.
- Container manager and feature toggle flows inherit that gate and also validate toggleable features through `ContainerManagerService` plus `FeatureRegistry`.
- Docker socket usage is high-privilege by design, but current rewrite wiring narrows it to fixed compose commands and fixed Docker API call sites; there is no direct user-supplied Docker endpoint or compose argument flow.
- Audit logging currently covers feature toggle attempts only.
- Export/import flows intentionally handle secrets; this task hardens temporary file and error-log handling so secrets are less likely to persist in `/logs`.

## Admin-Only Flows

Verified entry gate:

- `app/bot.php`
  - `Bot::input()` resets admin state, parses Telegram input, calls `auth()`, and runs `session()`/`action()` only when `$this->admin === true`.
  - `Bot::auth()` loads `app/config.php` and marks the sender as admin only when `from` is listed in `$c['admin']`.

Admin-gated sensitive operations found under `action()`:

- container manager menu and feature toggles
- restart and self-update orchestration
- import/export and backup scheduling
- branch/change-branch, logs, Docker cleanup, runtime restarts

Residual note:

- First-run bootstrap still auto-seeds the first sender into `app/config.php` when `admin` is empty. This is expected legacy bootstrap behavior, but it remains a trust-on-first-message model and should be considered during real deployment.

## Docker Socket And Command Surface

Verified fixed-scope runtime wiring:

- `src/Bootstrap/FeatureRuntimeFactory.php` creates `DockerContainerRuntime` with a fixed base command:
  - `docker compose -f /docker/docker-compose.yml -f /docker/compose`
- `src/Application/Feature/DockerContainerRuntime.php`
  - start path: `up -d --no-deps <registry services>`
  - stop path: `stop <registry services>`
  - remove path: `rm --force --stop <registry services>`
  - status path: `ps --all --format json <registry services>`
- Service names come from `FeatureRegistry`, not raw Telegram input.

Direct Docker socket client:

- `src/Infrastructure/Docker/DockerApiClient.php` talks to `/var/run/docker.sock`.
- Current `app/bot.php` call sites use fixed endpoints for image/container cleanup only.

Risk assessment:

- Mounting `/var/run/docker.sock` into runtime containers remains equivalent to host-level Docker control if the PHP process is compromised.
- Within current code, the rewrite did not introduce a generic user-driven Docker command channel; risk is architectural, not an obvious new injection path.

## Audit Log Coverage

Current coverage confirmed:

- `app/bot.php` `recordFeatureToggleAudit()` writes feature toggle attempts to SQLite `audit_log`.
- Payload includes actor/chat/username, requested action, result, and error text when present.

Gaps:

- restart
- import/export
- backup schedule changes
- branch/self-update actions

Recommendation:

- Keep future audit expansion targeted and structured in SQLite rather than appending raw operational payloads to flat log files.

## Secrets In Logs / Backups / Reports

Confirmed secret-bearing flows:

- `Bot::export()` includes certificates, WireGuard/Xray/OpenConnect/Shadowsocks state, MTProto secret, and other runtime credentials by design.
- `pinBackup()` uploads that export to Telegram for admins; this is an intentional secret-bearing backup channel.

Hardening applied in this task:

- `Bot::upload()` no longer writes export/download artifacts into `/logs/<name>` before Telegram upload.
- Temporary upload files now use `sys_get_temp_dir()` and are deleted in `finally`.
- `TelegramClient::request()` no longer logs raw request payloads to `/logs/requests_error`; it stores only a safe summary of keys, file-field presence, and text lengths.

Residual risks:

- `/logs/debug`, `/logs/requests`, and exception messages from SSH/runtime helpers can still contain sensitive operational data if debug paths are used manually.
- `SMOKE_TEST_REPORT.md` is untracked in this repo state; keep it sanitized before any future commit.

## Conclusion

- No obvious admin bypass found in current Telegram control flow.
- No obvious new Docker command injection path found in the feature-toggle rewrite.
- Main remaining security debt is privilege architecture (`docker.sock`, SSH control surface, trust-on-first-message bootstrap) and incomplete audit coverage for non-toggle admin actions.
