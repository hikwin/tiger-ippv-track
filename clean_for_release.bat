@echo off
title Release Cleanup Tool

echo ==================================================
echo              Release Cleanup Tool
echo ==================================================
echo.
echo WARNING: This script will permanently delete test files, logs, 
echo development databases, and installation lock files!
echo Please make sure this is the RELEASE directory, not your development folder.
echo.
echo Current Directory: %~dp0
echo.
set /p confirm="Are you sure you want to clean up this directory? (Type Y to confirm, other keys to exit): "
if /i "%confirm%" neq "Y" (
    echo Operation cancelled.
    pause
    exit /b
)

echo.
echo --------------------------------------------------
echo [1/4] Cleaning up generated tracking files...
echo --------------------------------------------------

:: Delete dynamically generated tracking scripts (e.g. tracking_*.php)
for %%f in ("%~dp0tracking_*.php") do (
    del /f /q "%%f"
    echo [Deleted generated file] %%~nxf
)

echo.
echo --------------------------------------------------
echo [2/4] Cleaning up installation files...
echo --------------------------------------------------
if exist "%~dp0install.lock" (
    del /f /q "%~dp0install.lock"
    echo [Deleted] install.lock
)

echo.
echo --------------------------------------------------
echo [3/4] Cleaning up dev databases and config...
echo --------------------------------------------------
if exist "%~dp0config\config.php" (
    del /f /q "%~dp0config\config.php"
    echo [Deleted] config\config.php
)

:: Delete development databases (stats_*.db)
for %%d in ("%~dp0data\stats_*.db") do (
    del /f /q "%%d"
    echo [Deleted dev database] %%~nxd
)

echo.
echo --------------------------------------------------
echo [4/4] Cleaning up log files...
echo --------------------------------------------------
if exist "%~dp0data\tracking_debug.log" (
    del /f /q "%~dp0data\tracking_debug.log"
    echo [Deleted] data\tracking_debug.log
)
if exist "%~dp0data\logs\" (
    rd /s /q "%~dp0data\logs"
    echo [Deleted log directory] data\logs\
)

echo.
echo ==================================================
echo Success! The project is now clean and ready for release.
echo ==================================================
pause
