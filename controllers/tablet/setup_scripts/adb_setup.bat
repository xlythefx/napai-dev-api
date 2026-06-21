@echo off
REM =====================================================================
REM  QuanTab Step 1: ADB Setup (Windows)
REM  Downloads Google's official Android platform-tools and verifies that
REM  your tablet is reachable over USB.
REM =====================================================================

setlocal enableextensions enabledelayedexpansion

set "TOOLS_DIR=%USERPROFILE%\quantab-adb"
set "ZIP_PATH=%TEMP%\platform-tools-latest-windows.zip"
set "ZIP_URL=https://dl.google.com/android/repository/platform-tools-latest-windows.zip"

echo.
echo === QuanTab ADB Setup ===
echo.
echo Install location: %TOOLS_DIR%
echo.

if exist "%TOOLS_DIR%\platform-tools\adb.exe" (
    echo [skip] platform-tools already installed.
    goto :verify
)

if not exist "%TOOLS_DIR%" mkdir "%TOOLS_DIR%"

echo [1/3] Downloading platform-tools from Google...
powershell -NoProfile -Command "try { Invoke-WebRequest -Uri '%ZIP_URL%' -OutFile '%ZIP_PATH%' -UseBasicParsing } catch { Write-Host $_.Exception.Message; exit 1 }"
if errorlevel 1 (
    echo.
    echo Download failed. Check your internet connection and try again.
    pause
    exit /b 1
)

echo [2/3] Extracting...
powershell -NoProfile -Command "try { Expand-Archive -Path '%ZIP_PATH%' -DestinationPath '%TOOLS_DIR%' -Force } catch { Write-Host $_.Exception.Message; exit 1 }"
if errorlevel 1 (
    echo Extraction failed.
    pause
    exit /b 1
)
del "%ZIP_PATH%" >nul 2>&1

:verify
echo [3/3] Verifying adb...
"%TOOLS_DIR%\platform-tools\adb.exe" version
if errorlevel 1 (
    echo adb did not run. Aborting.
    pause
    exit /b 1
)

echo.
echo Adding platform-tools to your PATH for this user...
for /f "usebackq tokens=2,*" %%A in (`reg query "HKCU\Environment" /v PATH 2^>nul ^| findstr /i "PATH"`) do set "OLD_PATH=%%B"
echo %OLD_PATH% | find /i "%TOOLS_DIR%\platform-tools" >nul
if errorlevel 1 (
    setx PATH "%OLD_PATH%;%TOOLS_DIR%\platform-tools" >nul
    echo PATH updated. Open a NEW Command Prompt to use 'adb' directly.
) else (
    echo PATH already contains platform-tools.
)

echo.
echo =====================================================================
echo  ADB is installed.
echo.
echo  NEXT STEPS on your tablet:
echo    1. Settings - About tablet - tap "Build number" 7 times to enable
echo       Developer Options.
echo    2. Settings - Developer Options - turn on "USB debugging".
echo    3. Connect the tablet to this PC with a USB cable.
echo    4. On the tablet, accept the "Allow USB debugging?" prompt.
echo.
echo  Then run Step 2 (install_kiosk.bat).
echo =====================================================================
echo.

echo Currently connected devices:
"%TOOLS_DIR%\platform-tools\adb.exe" devices
echo.
pause
endlocal
