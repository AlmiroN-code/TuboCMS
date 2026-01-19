@echo off
echo ========================================
echo RexTube - Запуск проекта
echo ========================================
echo.

echo [1/3] Очистка кэша Symfony...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console cache:clear
echo.

echo [2/3] Компиляция фронтенда...
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js dev
echo.

echo [3/3] Проверка статуса...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console about
echo.

echo ========================================
echo Проект готов к работе!
echo ========================================
echo.
echo URL: http://rextube.test:8080/
echo.
echo Тестовые пользователи:
echo - Админ: admin@rextube.test / admin123
echo - Пользователь: user@rextube.test / user123
echo.
echo Убедитесь, что Laragon запущен (MySQL + Nginx)
echo.
pause
