@echo off
echo ========================================
echo    Tiger Statistics Startup Script
echo ========================================
echo.

REM Set PHP path
set PHP_PATH=D:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe

REM Check if PHP is installed
"%PHP_PATH%" --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: PHP not detected, please check path: %PHP_PATH%
    echo If the path is incorrect, please modify the PHP_PATH variable in run.bat
    echo.
    pause
    exit /b 1
)

echo Detected PHP version:
"%PHP_PATH%" --version
echo.

REM Check project files
if not exist "index.php" (
    echo Error: index.php not found, please make sure you run this script in the correct project directory
    echo.
    pause
    exit /b 1
)

REM Create necessary directories
if not exist "data" mkdir data
if not exist "data\logs" mkdir data\logs
if not exist "config" mkdir config

echo Project directory structure check completed
echo.

REM Check if installed
if not exist "install.lock" (
    echo System not installed. Launching installation wizard...
    echo Please visit in your browser: http://localhost:8080/install.php
    echo.
) else (
    echo System is installed. Launching management interface...
    echo Admin Dashboard: http://localhost:8080/index.php?action=admin
    echo Public Dashboard: http://localhost:8080/
    echo.
)

echo Starting PHP built-in server...
echo Server address: http://localhost:8080
echo Press Ctrl+C to stop the server
echo.

REM Start PHP built-in server
"%PHP_PATH%" -S localhost:8080 -t .

echo.
echo Server stopped
pause 