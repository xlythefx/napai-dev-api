#!/usr/bin/env bash
# =====================================================================
#  QuanTab Step 2: Install and Kiosk Setup (Mac / Linux)
#  Downloads the latest QuanTab APK, installs it on the connected
#  tablet, and promotes it to device-owner so it cannot be closed or
#  bypassed by the customer.
#
#  PREREQUISITES:
#    * Step 1 (adb_setup.sh) has been run.
#    * The tablet has been FACTORY RESET and skipped through the welcome
#      screen WITHOUT adding a Google account. (Device-owner cannot be
#      set if any user account already exists on the device.)
#    * USB debugging is enabled and accepted on the tablet.
# =====================================================================
set -u

TOOLS_DIR="$HOME/quantab-adb"
ADB="$TOOLS_DIR/platform-tools/adb"
APK_PATH="/tmp/quantab.apk"
APK_URL="https://nap-ai.com/api/apk/vendotab"
PKG="com.xlythe.vendotab"
ADMIN="com.xlythe.vendotab/com.xlythe.vendotab.DeviceAdminReceiver"

if [ ! -x "$ADB" ]; then
    echo
    echo "adb not found at $ADB."
    echo "Please run Step 1 (adb_setup.sh) first."
    exit 1
fi

echo
echo "=== QuanTab Install & Kiosk Setup ==="
echo
echo "[1/5] Waiting for tablet over USB..."
"$ADB" wait-for-device || { echo "No tablet detected. Plug it in, accept the USB debugging prompt, then re-run."; exit 1; }

echo "[2/5] Downloading latest QuanTab APK..."
if command -v curl >/dev/null 2>&1; then
    curl -fL --progress-bar -o "$APK_PATH" "$APK_URL" || { echo "APK download failed."; exit 1; }
elif command -v wget >/dev/null 2>&1; then
    wget -O "$APK_PATH" "$APK_URL" || { echo "APK download failed."; exit 1; }
else
    echo "Neither curl nor wget is installed."; exit 1
fi

echo "[3/5] Installing APK on tablet..."
"$ADB" install -r "$APK_PATH" || { echo "APK install failed."; exit 1; }

echo "[4/5] Promoting QuanTab to device-owner (kiosk mode)..."
if ! "$ADB" shell dpm set-device-owner "$ADMIN"; then
    cat <<'EOF'

=====================================================================
 Could not set device-owner.

 The most common cause is that the tablet already has a user account
 (usually a Google account). Android only allows device-owner to be
 set on a FRESH device.

 FIX: Factory-reset the tablet (Settings -> System -> Reset), skip
 the welcome screen WITHOUT signing into Google, re-enable USB
 debugging, then run this script again.
=====================================================================
EOF
    exit 1
fi

echo "[5/5] Launching QuanTab on the tablet..."
"$ADB" shell am start -n "$PKG/.MainActivity"

rm -f "$APK_PATH"

cat <<'EOF'

=====================================================================
 Done. QuanTab is now installed as device-owner.
 The tablet will stay locked into the app until you run uninstall.sh.

 On the tablet, type your license serial on the activation screen
 and tap Activate.
=====================================================================
EOF
