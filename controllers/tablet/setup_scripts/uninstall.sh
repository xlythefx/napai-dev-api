#!/usr/bin/env bash
# =====================================================================
#  QuanTab Uninstall (Mac / Linux)
#  Removes device-owner status and uninstalls QuanTab from the tablet.
# =====================================================================
set -u

ADB="$HOME/quantab-adb/platform-tools/adb"
PKG="com.xlythe.vendotab"
ADMIN="com.xlythe.vendotab/com.xlythe.vendotab.DeviceAdminReceiver"

if [ ! -x "$ADB" ]; then
    echo "adb not found at $ADB. Please run adb_setup.sh first."
    exit 1
fi

echo
echo "=== QuanTab Uninstall ==="
echo

echo "[1/3] Waiting for tablet..."
"$ADB" wait-for-device || { echo "No tablet detected. Plug it in and re-run."; exit 1; }

echo "[2/3] Removing device-owner / device-admin..."
"$ADB" shell dpm remove-active-admin "$ADMIN" || true

echo "[3/3] Uninstalling QuanTab..."
"$ADB" uninstall "$PKG" || true

cat <<'EOF'

=====================================================================
 Done. The tablet is back to normal.
 You can now use it as a regular Android tablet, or factory-reset
 it if you plan to re-install QuanTab later.
=====================================================================
EOF
