#!/usr/bin/env bash
# =====================================================================
#  QuanTab Step 1: ADB Setup (Mac / Linux)
#  Downloads Google's official Android platform-tools and verifies that
#  your tablet is reachable over USB.
# =====================================================================
set -u

TOOLS_DIR="$HOME/quantab-adb"
case "$(uname -s)" in
    Darwin) ZIP_URL="https://dl.google.com/android/repository/platform-tools-latest-darwin.zip" ;;
    Linux)  ZIP_URL="https://dl.google.com/android/repository/platform-tools-latest-linux.zip"  ;;
    *)
        echo "Unsupported OS: $(uname -s). This script supports macOS and Linux."
        exit 1
        ;;
esac
ZIP_PATH="/tmp/quantab-platform-tools.zip"

echo
echo "=== QuanTab ADB Setup ==="
echo
echo "Install location: $TOOLS_DIR"
echo

if [ -x "$TOOLS_DIR/platform-tools/adb" ]; then
    echo "[skip] platform-tools already installed."
else
    mkdir -p "$TOOLS_DIR"

    echo "[1/3] Downloading platform-tools from Google..."
    if command -v curl >/dev/null 2>&1; then
        curl -fL --progress-bar -o "$ZIP_PATH" "$ZIP_URL" || { echo "Download failed."; exit 1; }
    elif command -v wget >/dev/null 2>&1; then
        wget -O "$ZIP_PATH" "$ZIP_URL" || { echo "Download failed."; exit 1; }
    else
        echo "Neither curl nor wget is installed. Install one and try again."
        exit 1
    fi

    echo "[2/3] Extracting..."
    if command -v unzip >/dev/null 2>&1; then
        unzip -oq "$ZIP_PATH" -d "$TOOLS_DIR" || { echo "Extraction failed."; exit 1; }
    else
        echo "'unzip' is not installed. Install it (e.g. 'sudo apt install unzip' or 'brew install unzip') and re-run."
        exit 1
    fi
    rm -f "$ZIP_PATH"
fi

echo "[3/3] Verifying adb..."
"$TOOLS_DIR/platform-tools/adb" version || { echo "adb did not run."; exit 1; }

# Add to PATH via the user's shell rc file (idempotent).
RC_FILE=""
case "$(basename "${SHELL:-}")" in
    zsh)  RC_FILE="$HOME/.zshrc"  ;;
    bash) RC_FILE="$HOME/.bashrc" ;;
esac
PATH_LINE="export PATH=\"\$HOME/quantab-adb/platform-tools:\$PATH\"  # quantab-adb"
if [ -n "$RC_FILE" ] && [ -f "$RC_FILE" ] && ! grep -q "quantab-adb" "$RC_FILE"; then
    echo "$PATH_LINE" >> "$RC_FILE"
    echo "Added platform-tools to PATH in $RC_FILE. Open a NEW terminal to use 'adb' directly."
fi

cat <<'EOF'

=====================================================================
 ADB is installed.

 NEXT STEPS on your tablet:
   1. Settings -> About tablet -> tap "Build number" 7 times to enable
      Developer Options.
   2. Settings -> Developer Options -> turn on "USB debugging".
   3. Connect the tablet to this computer with a USB cable.
   4. On the tablet, accept the "Allow USB debugging?" prompt.

 Then run Step 2 (install_kiosk.sh).
=====================================================================

EOF

echo "Currently connected devices:"
"$TOOLS_DIR/platform-tools/adb" devices
echo
