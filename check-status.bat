@echo off
echo ========================================
echo RexTube - Проверка состояния
echo ========================================
echo.

echo Проверка PHP...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe --version
echo.

echo Проверка Node.js...
D:\laragon\bin\nodejs\node-v22\node.exe --version
echo.

echo Проверка базы данных...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console doctrine:migrations:status
echo.

echo Информация о Symfony...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console about
echo.

pause
