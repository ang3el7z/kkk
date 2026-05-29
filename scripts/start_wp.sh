#!/bin/bash
set -e

cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &

WGCF_DIR="/etc/warp"
WGCF_CONF="$WGCF_DIR/wgcf-profile.conf"
SOCKS_PORT="${SOCKS_PORT:-1080}"
WARP_LICENSE_KEY=$(cat /config/pac.json | jq -r '.warp // empty')

mkdir -p "$WGCF_DIR"
cd "$WGCF_DIR"

if [ ! -f "$WGCF_CONF" ]; then
    echo "[warp] Registering WARP account..."
    wgcf register --accept-tos
    if [ -n "$WARP_LICENSE_KEY" ]; then
        echo "[warp] Applying license key..."
        WGCF_LICENSE_KEY="$WARP_LICENSE_KEY" wgcf update
    fi
    echo "[warp] Generating WireGuard profile..."
    wgcf generate
    echo "[warp] Stripping IPv6 from profile..."
    sed -i '/^Address.*:/d' "$WGCF_CONF"
    sed -i '/^AllowedIPs.*::/d' "$WGCF_CONF"
fi

echo "[warp] Starting WireGuard (WARP)..."
wg-quick up "$WGCF_CONF"

echo "[warp] Starting microsocks on port $SOCKS_PORT..."
exec microsocks -p "$SOCKS_PORT"
