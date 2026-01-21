#!/bin/bash

# Скрипт для проверки логов и статуса на сервере control.gmnode.ru
# Использование: ./check-server-logs.sh

SERVER="93.183.71.104"
USER="root"
DOMAIN="sexvids.online"
DB_USER="almiron"
DB_PASS="Mtn999Un86@"
DB_NAME="sexvids"

echo "=========================================="
echo "Проверка логов и статуса сервера"
echo "Сервер: $SERVER"
echo "Домен: $DOMAIN"
echo "=========================================="
echo ""

# Функция для выполнения команды на удаленном сервере
run_remote_cmd() {
    local title="$1"
    local cmd="$2"
    
    echo ">>> $title"
    echo "Команда: $cmd"
    echo "---"
    ssh -o ConnectTimeout=10 "$USER@$SERVER" "$cmd"
    echo ""
    echo ""
}

# 1. Логи Symfony
run_remote_cmd "1. ЛОГИ SYMFONY (последние 100 строк)" \
    "tail -100 /var/www/sexvids.online/var/log/prod.log"

# 2. Логи Nginx
run_remote_cmd "2. ЛОГИ NGINX (последние 50 строк)" \
    "tail -50 /var/log/nginx/sexvids.online_error.log"

# 3. Логи PHP-FPM
run_remote_cmd "3. ЛОГИ PHP-FPM (последние 50 строк)" \
    "tail -50 /var/log/php8.4-fpm.log"

# 4. Проверка таблиц БД
run_remote_cmd "4. ТАБЛИЦЫ БД" \
    "mysql -u $DB_USER -p'$DB_PASS' $DB_NAME -e 'SHOW TABLES;'"

# 5. Количество видео в БД
run_remote_cmd "5. КОЛИЧЕСТВО ВИДЕО В БД" \
    "mysql -u $DB_USER -p'$DB_PASS' $DB_NAME -e 'SELECT COUNT(*) as total_videos FROM video;'"

# 6. Статус Messenger Worker
run_remote_cmd "6. СТАТУС MESSENGER WORKER" \
    "systemctl status sexvids-messenger"

# 7. Дополнительно: проверка дискового пространства
run_remote_cmd "7. ДИСКОВОЕ ПРОСТРАНСТВО" \
    "df -h /var/www/sexvids.online"

# 8. Дополнительно: проверка процессов PHP-FPM
run_remote_cmd "8. ПРОЦЕССЫ PHP-FPM" \
    "ps aux | grep php-fpm | grep -v grep"

# 9. Дополнительно: проверка статуса Nginx
run_remote_cmd "9. СТАТУС NGINX" \
    "systemctl status nginx"

# 10. Дополнительно: проверка статуса MySQL
run_remote_cmd "10. СТАТУС MYSQL" \
    "systemctl status mysql"

echo "=========================================="
echo "Проверка завершена"
echo "=========================================="
