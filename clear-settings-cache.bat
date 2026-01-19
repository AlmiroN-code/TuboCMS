@echo off
echo Clearing settings cache...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console app:settings:clear-cache
echo.
echo Settings cache cleared!
pause
