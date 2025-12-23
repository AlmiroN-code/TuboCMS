@echo off
echo Clearing all caches...
echo.

echo [1/3] Clearing Symfony cache...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console cache:clear
echo.

echo [2/3] Warming up cache...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console cache:warmup
echo.

echo [3/3] Rebuilding frontend...
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js dev
echo.

echo All caches cleared and rebuilt!
pause
