@echo off
color 0C
echo.
echo ================================================
echo      Stopping WebDAV Server
echo ================================================
echo.

echo Stopping WebDAV server...
taskkill /F /IM php.exe 2>NUL
echo.
echo WebDAV server stopped!
echo.
echo Press any key to exit...
pause >NUL