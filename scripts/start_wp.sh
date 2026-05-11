#!/bin/bash
set -e

WGCF_DIR="/etc/warp"
WGCF_CONF="$WGCF_DIR/wgcf-profile.conf"
SOCKS_PORT="${SOCKS_PORT:-1080}"

mkdir -p "$WGCF_DIR"
cd "$WGCF_DIR"

if [ ! -f "$WGCF_CONF" ]; then
    echo "[warp] Registering WARP account..."
    wgcf register --accept-tos
    echo "[warp] Generating WireGuard profile..."
    wgcf generate
fi

echo "[warp] Stripping IPv6 from profile..."
sed -i '/^Address.*:/d' "$WGCF_CONF"
sed -i '/^AllowedIPs.*::/d' "$WGCF_CONF"

echo "[warp] Starting WireGuard (WARP)..."
wg-quick up "$WGCF_CONF"

echo "[warp] Starting microsocks on port $SOCKS_PORT..."
exec microsocks -p "$SOCKS_PORT"
