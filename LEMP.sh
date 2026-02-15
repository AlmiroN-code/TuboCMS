#!/bin/bash

# =============================================================================
# LEMP Stack Setup Script for Symfony 8 + RexTube
# Updated: 2026-02-11
# Ubuntu 24.04 LTS
# =============================================================================
set -e

# === КОНФИГУРАЦИЯ ===
HOSTNAME="control.gmnode.ru"
SERVER_IP="93.183.71.104"
DOMAIN="sexvids.online"
SITE_ROOT="/var/www/$DOMAIN"

DB_NAME="sexvids"
DB_USER="almiron"
DB_PASS="Mtn999Un86@"

ADMIN_EMAIL="admin@sexvids.online"
ADMIN_USERNAME="admin"
ADMIN_PASSWORD="admin123"

REPO_URL="https://github.com/AlmiroN-code/TuboCMS.git"

export COMPOSER_ALLOW_SUPERUSER=1

# Цвета
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

check_success() {
    if [ $? -ne 0 ]; then
        log_error "$1"
    fi
}

echo ""
echo "=============================================="
echo "  LEMP Stack для Symfony 8 + RexTube"
echo "  Server: $HOSTNAME ($SERVER_IP)"
echo "  Site: $DOMAIN"
echo "=============================================="
echo ""

# === 1. Проверка root ===
if [[ $EUID -ne 0 ]]; then
   log_error "Скрипт должен запускаться от root"
fi

# === 2. Hostname ===
log_info "Устанавливаю hostname: $HOSTNAME"
hostnamectl set-hostname "$HOSTNAME"
grep -q "$HOSTNAME" /etc/hosts || echo "$SERVER_IP $HOSTNAME" >> /etc/hosts
log_success "Hostname установлен"

# === 3. Обновление системы ===
log_info "Обновляю систему..."
apt update && apt upgrade -y
check_success "Ошибка обновления системы"
apt install -y curl wget gnupg2 software-properties-common ca-certificates \
    lsb-release apt-transport-https git unzip htop fail2ban ufw
check_success "Ошибка установки базовых пакетов"
log_success "Система обновлена"

# === 3.1. Настройка SWAP для обработки видео ===
log_info "Настраиваю SWAP для обработки видео..."
SWAP_SIZE="4G"
if [ ! -f /swapfile ]; then
    fallocate -l $SWAP_SIZE /swapfile
    check_success "Ошибка создания swap файла"
    chmod 600 /swapfile
    mkswap /swapfile
    check_success "Ошибка форматирования swap"
    swapon /swapfile
    check_success "Ошибка активации swap"
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo 'vm.swappiness=10' >> /etc/sysctl.conf
    echo 'vm.vfs_cache_pressure=50' >> /etc/sysctl.conf
    sysctl -p
    log_success "SWAP $SWAP_SIZE настроен"
else
    log_warn "SWAP уже настроен"
fi

# === 4. Nginx ===
if ! command -v nginx &> /dev/null; then
    log_info "Устанавливаю Nginx..."
    apt install -y nginx
    check_success "Ошибка установки Nginx"
    systemctl enable --now nginx
    check_success "Ошибка запуска Nginx"
    log_success "Nginx установлен"
else
    log_warn "Nginx уже установлен"
fi

# === 5. MariaDB ===
if ! command -v mariadb &> /dev/null; then
    log_info "Устанавливаю MariaDB..."
    apt install -y mariadb-server mariadb-client
    check_success "Ошибка установки MariaDB"
    systemctl enable --now mariadb
    check_success "Ошибка запуска MariaDB"
    log_success "MariaDB установлен"
else
    log_warn "MariaDB уже установлен"
fi

# === 6. PHP 8.4 + расширения ===
if ! command -v php8.4 &> /dev/null; then
    log_info "Добавляю репозиторий PHP 8.4..."
    add-apt-repository -y ppa:ondrej/php
    check_success "Ошибка добавления репозитория PHP"
    apt update
    check_success "Ошибка обновления пакетов"

    log_info "Устанавливаю PHP 8.4 и расширения..."
    apt install -y \
        php8.4-fpm php8.4-cli php8.4-common php8.4-mysql \
        php8.4-curl php8.4-gd php8.4-mbstring php8.4-xml \
        php8.4-zip php8.4-bcmath php8.4-intl php8.4-soap \
        php8.4-opcache php8.4-redis php8.4-memcached \
        php8.4-imagick php8.4-readline php8.4-xsl \
        php8.4-apcu php8.4-igbinary php8.4-msgpack php8.4-yaml
    check_success "Ошибка установки PHP 8.4"
    
    systemctl enable --now php8.4-fpm
    check_success "Ошибка запуска PHP-FPM"
    log_success "PHP 8.4 установлен"
else
    log_warn "PHP 8.4 уже установлен"
fi

# === 7. FFmpeg ===
if ! command -v ffmpeg &> /dev/null; then
    log_info "Устанавливаю FFmpeg..."
    apt install -y ffmpeg
    log_success "FFmpeg установлен"
else
    log_warn "FFmpeg уже установлен"
fi

# === 8. Redis ===
if ! command -v redis-server &> /dev/null; then
    log_info "Устанавливаю Redis..."
    apt install -y redis-server
    systemctl enable --now redis-server
    log_success "Redis установлен"
else
    log_warn "Redis уже установлен"
fi

# === 9. Memcached ===
if ! command -v memcached &> /dev/null; then
    log_info "Устанавливаю Memcached..."
    apt install -y memcached libmemcached-tools
    systemctl enable --now memcached
    log_success "Memcached установлен"
else
    log_warn "Memcached уже установлен"
fi

# === 10. Composer ===
if ! command -v composer &> /dev/null; then
    log_info "Устанавливаю Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    log_success "Composer установлен"
else
    log_warn "Composer уже установлен"
fi

# === 11. Node.js 22 LTS ===
if ! command -v node &> /dev/null; then
    log_info "Устанавливаю Node.js 22 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt install -y nodejs
    log_success "Node.js установлен"
else
    log_warn "Node.js уже установлен"
fi

# === 12. Переключение PHP CLI ===
log_info "Переключаю PHP CLI на 8.4..."
update-alternatives --set php /usr/bin/php8.4 2>/dev/null || true
log_success "PHP CLI = 8.4"

# === 13. Настройка БД ===
if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    log_info "Создаю БД $DB_NAME..."
    sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    check_success "Ошибка создания БД"
    sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    check_success "Ошибка создания пользователя"
    sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    check_success "Ошибка назначения прав"
    sudo mysql -e "FLUSH PRIVILEGES;"
    check_success "Ошибка применения прав"
    log_success "БД создана"
else
    log_warn "БД уже существует"
    sudo mysql -e "ALTER DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
fi

# === 14. Клонирование проекта ===
log_info "Клонирую RexTube..."
cd /root
if [ -d "$SITE_ROOT" ]; then
    rm -rf "$SITE_ROOT"
fi

git clone "$REPO_URL" "$SITE_ROOT"
cd "$SITE_ROOT"
log_success "Репозиторий склонирован"

# === 15. Конфигурация .env ===
log_info "Создаю .env.local..."
cat > "$SITE_ROOT/.env.local" << ENVEOF
APP_ENV=prod
APP_SECRET=$(openssl rand -hex 16)
APP_DEBUG=0

DATABASE_URL="mysql://$DB_USER:$DB_PASS@127.0.0.1:3306/$DB_NAME?serverVersion=10.11.0-MariaDB&charset=utf8mb4"

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

REDIS_URL=redis://localhost:6379
CACHE_ADAPTER=cache.adapter.redis

MAILER_DSN=null://null
MAILER_FROM=noreply@$DOMAIN

VAPID_PUBLIC_KEY=$(openssl rand -base64 32)
VAPID_PRIVATE_KEY=$(openssl rand -base64 32)
VAPID_SUBJECT=mailto:$ADMIN_EMAIL
ENVEOF
log_success ".env.local создан"

# === 16. Composer зависимости ===
log_info "Устанавливаю Composer зависимости..."
composer install --no-dev --optimize-autoloader --no-interaction
check_success "Ошибка установки Composer"
log_success "Composer установлен"

# === 17. NPM зависимости ===
log_info "Устанавливаю npm зависимости..."
npm ci
check_success "Ошибка установки npm"
log_success "npm установлен"

log_info "Собираю фронтенд..."
npm run build
check_success "Ошибка сборки фронтенда"
log_success "Фронтенд собран"

# Очистка кэша после сборки фронтенда
log_info "Очищаю кэш после сборки..."
rm -rf var/cache/prod/*
log_success "Кэш очищен"

# === 18. Создание схемы БД ===
log_info "Создаю схему БД через Doctrine..."
php bin/console doctrine:schema:create --no-interaction 2>&1 || {
    log_warn "Схема уже существует или ошибка создания, пробую обновить..."
    php bin/console doctrine:schema:update --force --no-interaction 2>&1 || log_warn "Не удалось обновить схему"
}
log_success "Схема БД создана"

# === 18.1. Миграции БД ===
log_info "Помечаю миграции как выполненные..."
php bin/console doctrine:migrations:version --add --all --no-interaction 2>/dev/null || true
log_success "Миграции помечены"

# === 19. Инициализация данных ===
log_info "Инициализирую роли и разрешения..."
php bin/console app:init-roles-permissions --no-interaction 2>/dev/null || log_warn "Команда не найдена, пропускаю"

log_info "Инициализирую профили кодирования..."
php bin/console app:video:init-profiles --no-interaction 2>/dev/null || log_warn "Команда не найдена, пропускаю"

# === 20. Создание админа ===
log_info "Создаю супер админа..."
ADMIN_HASH=$(php bin/console security:hash-password "$ADMIN_PASSWORD" --no-interaction 2>/dev/null | grep -oP '(?<=Hash\s{2})\S+' || echo '$2y$13$ZeO8Ob15CODnDSMu1G8aBuMOq8ZpBLiidu0Glz.2cbkISbE3hrdaa')

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << SQLEOF
INSERT INTO user (
    email, username, roles, password, 
    is_verified, is_premium, processing_priority, 
    subscribers_count, videos_count, total_views,
    country_manually_set, created_at, updated_at
)
VALUES (
    '$ADMIN_EMAIL',
    '$ADMIN_USERNAME',
    JSON_ARRAY('ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'),
    '$ADMIN_HASH',
    1, 1, 10, 
    0, 0, 0,
    0, NOW(), NOW()
) ON DUPLICATE KEY UPDATE 
    password='$ADMIN_HASH', 
    roles=JSON_ARRAY('ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'),
    is_verified=1,
    is_premium=1;
SQLEOF
check_success "Ошибка создания администратора"
log_success "Админ создан: $ADMIN_EMAIL / $ADMIN_PASSWORD"

# === 21. Messenger ===
log_info "Настраиваю Messenger..."
php bin/console messenger:setup-transports --no-interaction 2>/dev/null || true
check_success "Ошибка настройки Messenger"
log_success "Messenger настроен"

# === 22. Кэш ===
log_info "Прогреваю кэш..."
rm -rf var/cache/prod/* var/log/*
mkdir -p var/cache/prod var/log

# Создаём лог-файлы
touch var/log/prod.log var/log/dev.log
check_success "Ошибка создания лог-файлов"

chown -R www-data:www-data var/
chmod -R 755 var/
chmod -R 775 var/cache var/log

sudo -u www-data php bin/console cache:clear --env=prod --no-debug
sudo -u www-data php bin/console cache:warmup --env=prod --no-debug
log_success "Кэш прогрет"

# === 23. Права доступа ===
log_info "Настраиваю права..."
mkdir -p "$SITE_ROOT/public/media"/{videos,videos/tmp,posters,previews,avatars,site,covers,categories,series,playlists,models,animated}
chown -R www-data:www-data "$SITE_ROOT"
check_success "Ошибка установки владельца"
chmod -R 755 "$SITE_ROOT/var" "$SITE_ROOT/public/media"
check_success "Ошибка установки прав"
log_success "Права настроены"

# === 24. phpMyAdmin ===
if [ ! -d "/usr/share/phpmyadmin" ]; then
    log_info "Устанавливаю phpMyAdmin..."
    add-apt-repository -y ppa:phpmyadmin/ppa
    apt update
    export DEBIAN_FRONTEND=noninteractive
    apt install -y phpmyadmin
    
    BLOWFISH=$(openssl rand -base64 32)
    cat > /etc/phpmyadmin/config.inc.php << PMAEOF
<?php
\$cfg['blowfish_secret'] = '$BLOWFISH';
\$i = 0;
\$i++;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = 'localhost';
\$cfg['Servers'][\$i]['connect_type'] = 'socket';
\$cfg['Servers'][\$i]['socket'] = '/run/mysqld/mysqld.sock';
\$cfg['Servers'][\$i]['compress'] = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = false;
\$cfg['LoginCookieValidity'] = 1800;
\$cfg['MaxRows'] = 50;
\$cfg['SendErrorReports'] = 'never';
?>
PMAEOF
    log_success "phpMyAdmin установлен"
else
    log_warn "phpMyAdmin уже установлен"
fi

# === 25. Nginx конфигурация ===
log_info "Настраиваю Nginx..."
rm -f /etc/nginx/sites-enabled/default
rm -f /etc/nginx/sites-available/$DOMAIN
rm -f /etc/nginx/sites-enabled/$DOMAIN

cat > /etc/nginx/conf.d/rate_limiting.conf << 'RATELIMITEOF'
limit_req_zone $binary_remote_addr zone=login:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/m;
limit_req_zone $binary_remote_addr zone=upload:10m rate=2r/m;
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
RATELIMITEOF

cat > /etc/nginx/sites-available/$DOMAIN << 'NGINXEOF'
server {
    listen 80;
    listen [::]:80;
    server_name sexvids.online www.sexvids.online;

    root /var/www/sexvids.online/public;
    index index.php;

    access_log /var/log/nginx/sexvids.online_access.log;
    error_log /var/log/nginx/sexvids.online_error.log;

    client_max_body_size 2G;
    client_body_timeout 300s;
    client_header_timeout 60s;
    send_timeout 300s;

    limit_req zone=general burst=20 nodelay;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    server_tokens off;

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location ~* \.(mp4|webm|ogg|avi|mov)$ {
        expires 1d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    location /login {
        limit_req zone=login burst=10 nodelay;
        try_files $uri /index.php$is_args$args;
    }

    location /api/ {
        limit_req zone=api burst=10 nodelay;
        try_files $uri /index.php$is_args$args;
    }

    location /videos/upload {
        limit_req zone=upload burst=1 nodelay;
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ /\. {
        deny all;
    }

    location ~ /(var|vendor|config|migrations|tests)/ {
        deny all;
    }
}
NGINXEOF

cat > /etc/nginx/sites-available/$HOSTNAME << 'NGINXEOF2'
server {
    listen 80;
    listen [::]:80;
    server_name control.gmnode.ru 93.183.71.104;

    root /var/www/html;

    location = / {
        default_type text/html;
        return 200 '<html><head><title>Control Panel</title></head><body><h1>Server Control</h1><p><a href="/phpmyadmin">phpMyAdmin</a></p></body></html>';
    }

    location /phpmyadmin {
        alias /usr/share/phpmyadmin;
        index index.php;

        location ~ ^/phpmyadmin/(.+\.php)$ {
            alias /usr/share/phpmyadmin/$1;
            fastcgi_pass unix:/run/php/php8.4-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME /usr/share/phpmyadmin/$1;
            include fastcgi_params;
        }

        location ~* ^/phpmyadmin/(.+\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|svg|ttf|eot))$ {
            alias /usr/share/phpmyadmin/$1;
            expires 30d;
        }
    }
}
NGINXEOF2

ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/
ln -sf /etc/nginx/sites-available/$HOSTNAME /etc/nginx/sites-enabled/

nginx -t
check_success "Ошибка в конфигурации Nginx"

systemctl reload nginx
check_success "Ошибка перезагрузки Nginx"
log_success "Nginx настроен"

# === 26. PHP-FPM конфигурация ===
log_info "Настраиваю PHP-FPM..."
cat > /etc/php/8.4/fpm/conf.d/99-custom.ini << 'PHPINI'
upload_max_filesize = 2G
post_max_size = 2G
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
expose_php = Off
display_errors = Off
log_errors = On
session.cookie_httponly = 1
PHPINI

systemctl restart php8.4-fpm
check_success "Ошибка перезапуска PHP-FPM"
log_success "PHP-FPM настроен"

# === 27. Firewall ===
log_info "Настраиваю Firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 'Nginx Full'
ufw --force enable
log_success "Firewall настроен"

# === 28. Fail2Ban ===
log_info "Настраиваю Fail2Ban..."
cat > /etc/fail2ban/jail.local << 'F2BEOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
maxretry = 3

[nginx-http-auth]
enabled = true
F2BEOF
systemctl enable --now fail2ban
check_success "Ошибка запуска Fail2Ban"
log_success "Fail2Ban настроен"

# === 29. Certbot ===
if ! command -v certbot &> /dev/null; then
    log_info "Устанавливаю Certbot..."
    apt install -y certbot python3-certbot-nginx
    log_success "Certbot установлен"
fi

log_info "Получаю SSL сертификат..."
certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email $ADMIN_EMAIL --redirect 2>/dev/null || log_warn "SSL не удалось получить. Запусти вручную: certbot --nginx -d $DOMAIN -d www.$DOMAIN"

# === 30. Messenger Worker ===
log_info "Создаю Messenger Worker..."
cat > /etc/systemd/system/sexvids-messenger.service << 'SVCEOF'
[Unit]
Description=RexTube Messenger Worker
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/sexvids.online
ExecStart=/usr/bin/php8.4 /var/www/sexvids.online/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVCEOF

systemctl daemon-reload
check_success "Ошибка перезагрузки systemd"
systemctl enable sexvids-messenger
check_success "Ошибка включения Messenger Worker"
systemctl start sexvids-messenger
check_success "Ошибка запуска Messenger Worker"
log_success "Messenger Worker запущен"

# === 31. Перезапуск сервисов ===
log_info "Перезапускаю сервисы..."
systemctl restart nginx
check_success "Ошибка перезапуска Nginx"
log_success "Сервисы перезапущены"

# === 32. Сохранение данных ===
cat > /root/.server_credentials << CREDEOF
============================================
  RexTube Server Credentials
  Created: $(date)
============================================

SERVER:
  Hostname: $HOSTNAME
  IP: $SERVER_IP

DATABASE:
  DB: $DB_NAME
  User: $DB_USER
  Password: $DB_PASS

ADMIN:
  Email: $ADMIN_EMAIL
  Username: $ADMIN_USERNAME
  Password: $ADMIN_PASSWORD

URLS:
  Site: http://$DOMAIN
  phpMyAdmin: http://$HOSTNAME/phpmyadmin

PATHS:
  Root: $SITE_ROOT
  Logs: /var/log/nginx/

SSL:
  sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN
  sudo certbot --nginx -d $HOSTNAME

SERVICES:
  systemctl status sexvids-messenger
  journalctl -u sexvids-messenger -f

COMMANDS:
  php bin/console app:init-roles-permissions
  php bin/console app:video:init-profiles
  php bin/console cache:clear
  php bin/console messenger:consume async
============================================
CREDEOF
chmod 600 /root/.server_credentials
check_success "Ошибка установки прав на файл"

# === 33. Финальная проверка ===
log_info "Выполняю финальную проверку..."

systemctl is-active --quiet nginx || log_error "Nginx не запущен"
systemctl is-active --quiet mariadb || log_error "MariaDB не запущен"
systemctl is-active --quiet php8.4-fpm || log_error "PHP-FPM не запущен"
systemctl is-active --quiet sexvids-messenger || log_error "Messenger Worker не запущен"

curl -f -s http://localhost > /dev/null || log_error "Сайт не отвечает"

cd "$SITE_ROOT"
php bin/console debug:router > /dev/null 2>&1 || log_error "Symfony не работает"

log_success "Все сервисы работают корректно"

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  ✅ УСТАНОВКА ЗАВЕРШЕНА!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "${BLUE}Сайт:${NC} http://$DOMAIN"
echo -e "${BLUE}phpMyAdmin:${NC} http://$HOSTNAME/phpmyadmin"
echo -e "${BLUE}Админ:${NC} $ADMIN_EMAIL / $ADMIN_PASSWORD"
echo ""
echo -e "${YELLOW}Учетные данные сохранены в:${NC} /root/.server_credentials"
echo ""
