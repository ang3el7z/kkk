# Migration Plan

## Strategy

Use a one-shot importer:

```bash
php bin/import-legacy.php --from=/config --db=/data/vpnbot.sqlite
```

After import, normal runtime reads SQLite. Legacy files remain only as generated daemon config or backup source.

## Database Location

- SQLite path: `/data/vpnbot.sqlite`
- Docker volume: `data:/data`
- Backup should include `/data/vpnbot.sqlite` plus generated daemon configs when needed.

## Initial Tables

```text
settings(key, value_json, updated_at)
admins(id, telegram_id, username, created_at)
features(id, enabled, created_at, updated_at)
wireguard_instances(id, service, title, amnezia_enabled, dns, mtu, endpoint_mode)
wireguard_clients(id, instance_id, name, enabled, config_json, created_at, updated_at)
xray_users(id, email, uuid, flow, enabled, expires_at, config_json, created_at, updated_at)
xray_stats(id, scope, subject, upload, download, period, updated_at)
openconnect_users(id, username, enabled, created_at, updated_at)
lists(id, type, value, enabled, created_at)
reply_sessions(id, telegram_user_id, message_id, callback, args_json, created_at)
audit_log(id, actor_id, action, payload_json, created_at)
```

## Legacy Inputs

- `app/config.php`: bot token, admins, debug.
- `/config/pac.json`: settings, feature settings, lists, templates.
- `/config/clients.json`: WireGuard instance `wg`.
- `/config/clients1.json`: WireGuard instance `wg1`.
- `/config/xray.json`: Xray users and routing config.
- `/config/xray.stats`: Xray stats.
- `/config/ocserv.passwd`: OpenConnect users.
- `/config/AdGuardHome.yaml`: AdGuard settings.
- `/config/hysteria.yaml`: Hysteria settings.
- `/config/mtprotosecret`, `/config/mtprotodomain`: MTProto settings.
- `/certs/cert_private`, `/certs/cert_public`: certificate material.

## Import Rules

- Import missing optional files as empty defaults.
- Preserve raw legacy fragments in `settings` during first version if schema is not ready.
- Validate JSON/YAML before writing DB.
- Never mutate legacy files during import.
- Write an import report to `/logs/import-legacy.log`.

## Generated Files After Migration

- `/config/wg0.conf`, `/config/wg1.conf`
- `/config/xray.json`
- `/config/ssserver.json`, `/config/sslocal.json`
- `/config/AdGuardHome.yaml`
- `/config/hysteria.yaml`
- `/config/ocserv.conf`, `/config/ocserv.passwd`
- `/config/nginx.conf`, `/config/upstream.conf`
- `/docker/compose`

## Rollback

- Stop containers.
- Restore previous `/config`, `/certs`, and `/docker/compose` from backup.
- Start old stack.

The rewrite should keep a backup command before first import, but runtime code should not depend on rollback paths.
