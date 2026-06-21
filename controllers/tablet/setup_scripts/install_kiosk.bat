@echo off
REM =====================================================================
REM  QuanTab Step 2: Install and Kiosk Setup (Windows)
REM  Downloads the latest QuanTab APK, installs it on the connected
REM  tablet, and promotes it to device-owner so it cannot be closed or
REM  bypassed by the customer.
REM
REM  PREREQUISITES:
REM    * Step 1 (adb_setup.bat) has been run.
REM    * The tablet has been FACTORY RESET and skipped through the welcome
REM      screen WITHOUT adding a Google account. (Device-owner cannot be
REM      set if any user account already exists on the device.)
REM    * USB debugging is enabled and accepted on the tablet.
REM =====================================================================

setlocal enableextensions enabledelayedexpansion

set "TOOLS_DIR=%USERPROFILE%\quantab-adb"
set "ADB=%TOOLS_DIR%\platform-tools\adb.exe"
set "APK_PATH=%TEMP%\quantab.apk"
set "APK_URL=https://nap-ai.com/api/apk/vendotab"
set "PKG=com.xlythe.vendotab"
set "ADMIN=com.xlythe.vendotab/com.xlythe.vendotab.DeviceAdminReceiver"

if not exist "%ADB%" (
    echo.
    echo adb.exe not found at %ADB%.
    echo Please run Step 1 ^(adb_setup.bat^) first.
    pause
    exit /b 1
)

echo.
echo === QuanTab Install ^& Kiosk Setup ===
echo.
echo [1/5] Waiting for tablet over USB...
"%ADB%" wait-for-device
if errorlevel 1 (
    echo No tablet detected. Plug it in, accept the USB debugging prompt, then re-run.
    pause
    exit /b 1
)

echo [2/5] Downloading latest QuanTab APK...
powershell -NoProfile -Command "try { Invoke-WebRequest -Uri '%APK_URL%' -OutFile '%APK_PATH%' -UseBasicParsing } catch { Write-Host $_.Exception.Message; exit 1 }"
if errorlevel 1 (
    echo APK download failed. Check internet connection.
    pause
    exit /b 1
)

echo [3/5] Installing APK on tablet...
"%ADB%" install -r "%APK_PATH%"
if errorlevel 1 (
    echo APK install failed.
    pause
    exit /b 1
)

echo [4/5] Promoting QuanTab to device-owner ^(kiosk mode^)...
"%ADB%" shell dpm set-device-owner %ADMIN%
if errorlevel 1 (
    echo.
    echo =====================================================================
    echo  Could not set device-owner.
    echo.
    echo  The most common cause is that the tablet already has a user account
    echo  ^(usually a Google account^). Android only allows device-owner to be
    echo  set on a FRESH device.
    echo.
    echo  FIX: Factory-reset the tablet ^(Settings -^> System -^> Reset^), skip
    echo  the welcome screen WITHOUT signing into Google, re-enable USB
    echo  debugging, then run this script again.
    echo =====================================================================
    pause
    exit /b 1
)

echo [5/5] Launching QuanTab on the tablet...
"%ADB%" shell am start -n %PKG%/.MainActivity

del "%APK_PATH%" >nul 2>&1

echo.
echo =====================================================================
echo  Done. QuanTab is now installed as device-owner.
echo  The tablet will stay locked into the app until you run uninstall.bat.
echo.
echo  On the tablet, type your license serial on the activation screen
echo  and tap Activate.
echo =====================================================================
echo.
pause
endlocal
