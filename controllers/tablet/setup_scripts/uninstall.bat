@echo off
REM =====================================================================
REM  QuanTab Uninstall (Windows)
REM  Removes device-owner status and uninstalls QuanTab from the tablet.
REM =====================================================================

setlocal enableextensions

set "ADB=%USERPROFILE%\quantab-adb\platform-tools\adb.exe"
set "PKG=com.xlythe.vendotab"
set "ADMIN=com.xlythe.vendotab/com.xlythe.vendotab.DeviceAdminReceiver"

if not exist "%ADB%" (
    echo adb.exe not found. Please run adb_setup.bat first.
    pause
    exit /b 1
)

echo.
echo === QuanTab Uninstall ===
echo.

echo [1/3] Waiting for tablet...
"%ADB%" wait-for-device
if errorlevel 1 (
    echo No tablet detected. Plug it in and re-run.
    pause
    exit /b 1
)

echo [2/3] Removing device-owner / device-admin...
"%ADB%" shell dpm remove-active-admin %ADMIN%

echo [3/3] Uninstalling QuanTab...
"%ADB%" uninstall %PKG%

echo.
echo =====================================================================
echo  Done. The tablet is back to normal.
echo  You can now use it as a regular Android tablet, or factory-reset
echo  it if you plan to re-install QuanTab later.
echo =====================================================================
echo.
pause
endlocal
