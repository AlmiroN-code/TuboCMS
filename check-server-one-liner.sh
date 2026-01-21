#!/bin/bash

# Одна команда для проверки всех логов и статусов
# Выполнить на локальной машине: ssh root@93.183.71.104 < check-server-one-liner.sh

echo "=========================================="
echo "ПРОВЕРКА ЛОГОВ И СТАТУСА СЕРВЕРА"
echo "=========================================="
echo ""

echo ">>> 1. ЛОГИ SYMFONY (последние 100 строк)"
echo "---"
tail -100 /var/www/sexvids.online/var/log/prod.log 2>/dev/null || echo "Файл не найден"
echo ""
echo ""

echo ">>> 2. ЛОГИ NGINX (последние 50 строк)"
echo "---"
tail -50 /var/log/nginx/sexvids.online_error.log 2>/dev/null || echo "Файл не найден"
echo ""
echo ""

echo ">>> 3. ЛОГИ PHP-FPM (последние 50 строк)"
echo "---"
tail -50 /var/log/php8.4-fpm.log 2>/dev/null || echo "Файл не найден"
echo ""
echo ""

echo ">>> 4. ТАБЛИЦЫ БД"
echo "---"
mysql -u almiron -p'Mtn999Un86@' sexvids -e "SHOW TABLES;" 2>/dev/null || echo "Ошибка подключения к БД"
echo ""
echo ""

echo ">>> 5. КОЛИЧЕСТВО ВИДЕО В БД"
echo "---"
mysql -u almiron -p'Mtn999Un86@' sexvids -e "SELECT COUNT(*) as total_videos FROM video;" 2>/dev/null || echo "Ошибка запроса"
echo ""
echo ""

echo ">>> 6. СТАТУС MESSENGER WORKER"
echo "---"
systemctl status sexvids-messenger 2>/dev/null || echo "Сервис не найден"
echo ""
echo ""

echo ">>> 7. ДИСКОВОЕ ПРОСТРАНСТВО"
echo "---"
df -h /var/www/sexvids.online
echo ""
echo ""

echo ">>> 8. ПРОЦЕССЫ PHP-FPM"
echo "---"
ps aux | grep php-fpm | grep -v grep
echo ""
echo ""

echo ">>> 9. СТАТУС NGINX"
echo "---"
systemctl status nginx
echo ""
echo ""

echo ">>> 10. СТАТУС MYSQL"
echo "---"
systemctl status mysql
echo ""
echo ""

echo "=========================================="
echo "Проверка завершена"
echo "=========================================="
