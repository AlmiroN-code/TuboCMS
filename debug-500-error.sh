#!/bin/bash

# Скрипт для диагностики ошибки 500 на странице /videos/
# Использование: ./debug-500-error.sh

SERVER="93.183.71.104"
USER="root"
DOMAIN="sexvids.online"
SITE_ROOT="/var/www/$DOMAIN"

echo "=========================================="
echo "ДИАГНОСТИКА ОШИБКИ 500 НА /videos/"
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

# 1. Проверяем логи Symfony (последние ошибки)
run_remote_cmd "1. ЛОГИ SYMFONY - ПОСЛЕДНИЕ ОШИБКИ" \
    "tail -50 $SITE_ROOT/var/log/prod.log | grep -i error"

# 2. Проверяем логи Nginx для домена
run_remote_cmd "2. ЛОГИ NGINX - ОШИБКИ" \
    "tail -50 /var/log/nginx/${DOMAIN}_error.log"

# 3. Проверяем логи PHP-FPM
run_remote_cmd "3. ЛОГИ PHP-FPM - ОШИБКИ" \
    "tail -50 /var/log/php8.4-fpm.log | grep -i error"

# 4. Проверяем права доступа к файлам
run_remote_cmd "4. ПРАВА ДОСТУПА К ФАЙЛАМ" \
    "ls -la $SITE_ROOT/ | head -20"

# 5. Проверяем права на папку var
run_remote_cmd "5. ПРАВА НА ПАПКУ VAR" \
    "ls -la $SITE_ROOT/var/"

# 6. Проверяем кеш Symfony
run_remote_cmd "6. ПРОВЕРКА КЕША SYMFONY" \
    "ls -la $SITE_ROOT/var/cache/"

# 7. Проверяем подключение к БД
run_remote_cmd "7. ПОДКЛЮЧЕНИЕ К БД" \
    "cd $SITE_ROOT && php bin/console doctrine:database:create --if-not-exists"

# 8. Проверяем таблицы в БД
run_remote_cmd "8. ТАБЛИЦЫ В БД" \
    "cd $SITE_ROOT && php bin/console doctrine:schema:validate"

# 9. Проверяем статус сервисов
run_remote_cmd "9. СТАТУС NGINX" \
    "systemctl status nginx --no-pager -l"

run_remote_cmd "10. СТАТУС PHP-FPM" \
    "systemctl status php8.4-fpm --no-pager -l"

run_remote_cmd "11. СТАТУС MYSQL" \
    "systemctl status mysql --no-pager -l"

# 12. Тестируем простую команду Symfony
run_remote_cmd "12. ТЕСТ SYMFONY КОМАНДЫ" \
    "cd $SITE_ROOT && php bin/console debug:router | head -10"

# 13. Проверяем переменные окружения
run_remote_cmd "13. ПЕРЕМЕННЫЕ ОКРУЖЕНИЯ" \
    "cd $SITE_ROOT && cat .env.local"

# 14. Проверяем composer autoload
run_remote_cmd "14. COMPOSER AUTOLOAD" \
    "cd $SITE_ROOT && composer dump-autoload --optimize"

# 15. Очищаем кеш
run_remote_cmd "15. ОЧИСТКА КЕША" \
    "cd $SITE_ROOT && php bin/console cache:clear --env=prod"

echo "=========================================="
echo "ДИАГНОСТИКА ЗАВЕРШЕНА"
echo "=========================================="