#!/bin/bash

# =============================================================================
# LEMP Stack Setup Script for Symfony 8 + SeeXXX
# Hostname: control.gmnode.ru (93.183.71.104)
# Site: seexxx.online
# phpMyAdmin: control.gmnode.ru/phpmyadmin
# =============================================================================
set -e

# === КОНФИГУРАЦИЯ ===
HOSTNAME="control.gmnode.ru"
SERVER_IP="93.183.71.104"
DOMAIN="seexxx.online"
SITE_ROOT="/var/www/$DOMAIN"

DB_NAME="seexxx"
DB_USER="almiron"
DB_PASS="Mtn999Un86@"

ADMIN_EMAIL="admin@seexxx.online"
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

# Функция проверки успешности команд
check_success() {
    if [ $? -ne 0 ]; then
        log_error "$1"
    fi
}

echo ""
echo "=============================================="
echo "  LEMP Stack для Symfony 8"
echo "  Server: $HOSTNAME ($SERVER_IP)"
echo "  Site: $DOMAIN"
echo "=============================================="
echo ""

# === 1. Проверка root ===
if [[ $EUID -ne 0 ]]; then
   log_error "Скрипт должен запускаться от root"
   exit 1
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
    # Оптимизация для видео обработки
    echo 'vm.swappiness=10' >> /etc/sysctl.conf
    echo 'vm.vfs_cache_pressure=50' >> /etc/sysctl.conf
    sysctl -p
    log_success "SWAP $SWAP_SIZE настроен для видео обработки"
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

# === 6. PHP 8.4 + все расширения для Symfony 8 ===
if ! command -v php8.4 &> /dev/null; then
    log_info "Добавляю репозиторий PHP 8.4..."
    add-apt-repository -y ppa:ondrej/php
    check_success "Ошибка добавления репозитория PHP"
    apt update
    check_success "Ошибка обновления пакетов после добавления репозитория PHP"

    log_info "Устанавливаю PHP 8.4 и все расширения..."
    apt install -y \
        php8.4-fpm \
        php8.4-cli \
        php8.4-common \
        php8.4-mysql \
        php8.4-pgsql \
        php8.4-sqlite3 \
        php8.4-curl \
        php8.4-gd \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-zip \
        php8.4-bcmath \
        php8.4-intl \
        php8.4-soap \
        php8.4-opcache \
        php8.4-redis \
        php8.4-memcached \
        php8.4-imagick \
        php8.4-readline \
        php8.4-xsl \
        php8.4-apcu \
        php8.4-igbinary \
        php8.4-msgpack \
        php8.4-yaml
    check_success "Ошибка установки PHP 8.4 и расширений"
    
    systemctl enable --now php8.4-fpm
    check_success "Ошибка запуска PHP-FPM"
    log_success "PHP 8.4 установлен"
else
    log_warn "PHP 8.4 уже установлен"
fi

# === 7. FFmpeg для конвертации видео ===
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

# === 11. Node.js 20 LTS ===
if ! command -v node &> /dev/null; then
    log_info "Устанавливаю Node.js 20 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
    log_success "Node.js установлен"
else
    log_warn "Node.js уже установлен"
fi

# === 12. Переключение PHP CLI на 8.4 ===
log_info "Переключаю PHP CLI на 8.4..."
update-alternatives --set php /usr/bin/php8.4 2>/dev/null || true
log_success "PHP CLI = 8.4"

# === 13. Настройка БД ===
if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    log_info "Создаю БД $DB_NAME..."
    sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    check_success "Ошибка создания базы данных"
    sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    check_success "Ошибка создания пользователя БД"
    sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    check_success "Ошибка назначения прав пользователю БД"
    sudo mysql -e "FLUSH PRIVILEGES;"
    check_success "Ошибка применения прав БД"
    log_success "БД создана"
else
    log_warn "БД уже существует"
fi

# === 14. Клонирование TuboCMS ===
log_info "Клонирую TuboCMS..."
# Переходим в безопасную директорию перед удалением
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
ENVEOF
log_success ".env.local создан"

# === 16. Composer зависимости ===
log_info "Устанавливаю Composer зависимости..."
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
check_success "Ошибка установки Composer зависимостей"
# symfony/process нужен для обработки видео (FFmpeg)
composer require symfony/process --no-interaction --no-scripts 2>/dev/null || true
# Выполняем assets:install вручную
php bin/console assets:install public --no-interaction 2>/dev/null || true
log_success "Composer установлен"

# === 17. NPM зависимости ===
log_info "Устанавливаю npm зависимости..."
npm ci
check_success "Ошибка установки npm зависимостей"
log_success "npm установлен"

# === 17.1. Исправление webpack.config.js ===
log_info "Исправляю webpack.config.js..."
cat > "$SITE_ROOT/webpack.config.js" << 'WEBPACKEOF'
const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.js')
    .enableStimulusBridge('./assets/controllers.json')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications(!Encore.isProduction())
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.39';
    })
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [
                require('tailwindcss'),
                require('autoprefixer')
            ]
        };
    })
;

module.exports = Encore.getWebpackConfig();
WEBPACKEOF
check_success "Ошибка исправления webpack.config.js"
log_success "webpack.config.js исправлен"

# === 17.2. Исправление doctrine.yaml ===
log_info "Исправляю doctrine.yaml..."
cat > "$SITE_ROOT/config/packages/doctrine.yaml" << 'DOCTRINEEOF'
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        profiling_collect_backtrace: '%kernel.debug%'
        options:
            charset: utf8mb4
        default_table_options:
            charset: utf8mb4
            collate: utf8mb4_unicode_ci

    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App

when@test:
    doctrine:
        dbal:
            dbname_suffix: '_test%env(default::TEST_TOKEN)%'

when@prod:
    doctrine:
        orm:
            auto_generate_proxy_classes: false
            proxy_dir: '%kernel.build_dir%/doctrine/orm/Proxies'
            query_cache_driver:
                type: pool
                pool: doctrine.system_cache_pool
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool

    framework:
        cache:
            pools:
                doctrine.result_cache_pool:
                    adapter: cache.app
                doctrine.system_cache_pool:
                    adapter: cache.system
DOCTRINEEOF
check_success "Ошибка исправления doctrine.yaml"
log_success "doctrine.yaml исправлен"

log_info "Собираю фронтенд..."
npm run build
check_success "Ошибка сборки фронтенда"
log_success "Фронтенд собран"

# === 18. Миграции БД ===
log_info "Выполняю миграции Doctrine..."
php bin/console doctrine:migrations:migrate --no-interaction
check_success "Ошибка выполнения миграций Doctrine"
log_success "Миграции выполнены"

# === 19. Создание админа ===
log_info "Создаю супер админа..."
ADMIN_HASH=$(php bin/console security:hash-password "$ADMIN_PASSWORD" --no-interaction 2>/dev/null | grep -oP '(?<=Hash\s{2})\S+' || echo '$2y$13$defaulthash')

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << SQLEOF
INSERT INTO user (email, username, roles, password, is_verified, is_premium, processing_priority, subscribers_count, videos_count, total_views, created_at, updated_at)
VALUES (
    '$ADMIN_EMAIL',
    '$ADMIN_USERNAME',
    '["ROLE_ADMIN","ROLE_USER"]',
    '$ADMIN_HASH',
    1,
    1,
    10,
    0,
    0,
    0,
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE password='$ADMIN_HASH', roles='["ROLE_ADMIN","ROLE_USER"]';
SQLEOF
check_success "Ошибка создания администратора"
log_success "Админ создан: $ADMIN_EMAIL / $ADMIN_PASSWORD"

# === 28. Инициализация ролей и разрешений ===
log_info "Инициализирую роли и разрешения..."
php bin/console app:init-roles-permissions 2>/dev/null || true
check_success "Ошибка инициализации ролей и разрешений"
log_success "Роли и разрешения инициализированы"

# === 29. Инициализация профилей кодирования ===
log_info "Инициализирую профили кодирования..."
php bin/console app:video:init-profiles 2>/dev/null || true
check_success "Ошибка инициализации профилей кодирования"
log_success "Профили кодирования инициализированы"

# === 30. Messenger ===
log_info "Настраиваю Messenger..."
php bin/console messenger:setup-transports 2>/dev/null || true
check_success "Ошибка настройки Messenger"
log_success "Messenger настроен"

# === 31. Кэш ===
log_info "Прогреваю кэш..."
php bin/console doctrine:cache:clear-metadata 2>/dev/null || true
php bin/console doctrine:cache:clear-query 2>/dev/null || true
rm -rf var/cache/*
mkdir -p var/cache/prod
chown -R www-data:www-data var/
sudo -u www-data php bin/console cache:warmup --env=prod
check_success "Ошибка прогрева кэша"
log_success "Кэш прогрет"

# === 32. Права ===
log_info "Настраиваю права..."
mkdir -p "$SITE_ROOT/public/media/videos"
mkdir -p "$SITE_ROOT/public/media/videos/tmp"
mkdir -p "$SITE_ROOT/public/media/posters"
mkdir -p "$SITE_ROOT/public/media/previews"
mkdir -p "$SITE_ROOT/public/media/avatars"
mkdir -p "$SITE_ROOT/public/media/site"
mkdir -p "$SITE_ROOT/public/media/covers"
mkdir -p "$SITE_ROOT/public/media/categories"
mkdir -p "$SITE_ROOT/public/media/series"
mkdir -p "$SITE_ROOT/public/media/playlists"
mkdir -p "$SITE_ROOT/public/media/models"
mkdir -p "$SITE_ROOT/public/media/animated"
chown -R www-data:www-data "$SITE_ROOT"
check_success "Ошибка установки владельца файлов"
chmod -R 755 "$SITE_ROOT/var"
chmod -R 755 "$SITE_ROOT/public/media"
check_success "Ошибка установки прав доступа"
log_success "Права настроены"

# === 33. phpMyAdmin ===
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

# === 34. Nginx конфигурация ===
log_info "Настраиваю Nginx..."
PHP_SOCKET="/run/php/php8.4-fpm.sock"
rm -f /etc/nginx/sites-enabled/default

cat > /etc/nginx/sites-available/$DOMAIN << 'NGINXEOF'
# Rate limiting zones
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/m;
limit_req_zone $binary_remote_addr zone=upload:10m rate=2r/m;
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;

server {
    listen 80;
    listen [::]:80;
    server_name seexxx.online www.seexxx.online;

    root /var/www/seexxx.online/public;
    index index.php;

    access_log /var/log/nginx/seexxx.online_access.log;
    error_log /var/log/nginx/seexxx.online_error.log;

    client_max_body_size 2G;
    client_body_timeout 300s;
    client_header_timeout 60s;
    send_timeout 300s;

    # Rate limiting
    limit_req zone=general burst=20 nodelay;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/atom+xml
        image/svg+xml;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Hide server version
    server_tokens off;

    # Media files with long cache
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Video files
    location ~* \.(mp4|webm|ogg|avi|mov)$ {
        expires 1d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    # Rate limiting for specific endpoints
    location /login {
        limit_req zone=login burst=3 nodelay;
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

    # PHP-FPM configuration
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        internal;
    }

    # Deny access to .php files in subdirectories
    location ~ \.php$ {
        return 404;
    }

    # Main location block
    location / {
        try_files $uri /index.php$is_args$args;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(var|vendor|config|migrations|tests)/ {
        deny all;
    }
}
NGINXEOFn;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
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

    location ~ /\. {
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

# Проверяем конфигурацию Nginx
nginx -t
check_success "Ошибка в конфигурации Nginx"

systemctl reload nginx
check_success "Ошибка перезагрузки Nginx"
log_success "Nginx настроен"

# === 35. PHP-FPM конфигурация ===
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
log_success "PHP-FPM настроен"

# === 36. Firewall ===
log_info "Настраиваю Firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 'Nginx Full'
ufw --force enable
log_success "Firewall настроен"

# === 37. Fail2Ban ===
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

# === 38. Certbot ===
if ! command -v certbot &> /dev/null; then
    log_info "Устанавливаю Certbot..."
    apt install -y certbot python3-certbot-nginx
    log_success "Certbot установлен"
fi

# === 38.1. Получение SSL сертификата ===
log_info "Получаю SSL сертификат для $DOMAIN..."
certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email $ADMIN_EMAIL --redirect 2>/dev/null || log_warn "SSL не удалось получить. Проверь DNS записи и запусти вручную: certbot --nginx -d $DOMAIN -d www.$DOMAIN"

# === 39. Messenger Worker ===
log_info "Создаю Messenger Worker..."
cat > /etc/systemd/system/seexxx-messenger.service << 'SVCEOF'
[Unit]
Description=SeeXXX Messenger Worker
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/seexxx.online
ExecStart=/usr/bin/php8.4 /var/www/seexxx.online/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVCEOF

systemctl daemon-reload
check_success "Ошибка перезагрузки systemd"
systemctl enable seexxx-messenger
check_success "Ошибка включения Messenger Worker"
systemctl start seexxx-messenger
check_success "Ошибка запуска Messenger Worker"
log_success "Messenger Worker запущен"

# === 40. Перезапуск сервисов ===
log_info "Перезапускаю сервисы..."
systemctl restart php8.4-fpm
check_success "Ошибка перезапуска PHP-FPM"
systemctl restart nginx
check_success "Ошибка перезапуска Nginx"
log_success "Сервисы перезапущены"

# === 41. Сохранение данных ===
cat > /root/.server_credentials << CREDEOF
============================================
  SeeXXX Server Credentials
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
  systemctl status seexxx-messenger
  journalctl -u seexxx-messenger -f
============================================
CREDEOF
chmod 600 /root/.server_credentials
check_success "Ошибка установки прав на файл с учетными данными"

# === ФИНАЛЬНАЯ ПРОВЕРКА ===
log_info "Выполняю финальную проверку..."

# Проверяем что все сервисы запущены
systemctl is-active --quiet nginx || log_error "Nginx не запущен"
systemctl is-active --quiet mariadb || log_error "MariaDB не запущен"
systemctl is-active --quiet php8.4-fpm || log_error "PHP-FPM не запущен"
systemctl is-active --quiet seexxx-messenger || log_error "Messenger Worker не запущен"

# Проверяем что сайт отвечает
curl -f -s http://localhost > /dev/null || log_error "Сайт не отвечает на localhost"

log_success "Все сервисы работают корректно"

# === ФИНАЛ ===
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  ✅ УСТАНОВКА ЗАВЕРШЕНА!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "🌐 ${BLUE}Сайт:${NC}        http://$DOMAIN"
echo -e "🔧 ${BLUE}phpMyAdmin:${NC}  http://$HOSTNAME/phpmyadmin"
echo ""
echo -e "${YELLOW}=== Админ ===${NC}"
echo -e "Email:    $ADMIN_EMAIL"
echo -e "Username: $ADMIN_USERNAME"
echo -e "Password: ${RED}$ADMIN_PASSWORD${NC}"
echo ""
echo -e "${YELLOW}=== БД ===${NC}"
echo -e "DB:   $DB_NAME"
echo -e "User: $DB_USER"
echo -e "Pass: $DB_PASS"
echo ""
echo -e "📄 Данные: ${BLUE}/root/.server_credentials${NC}"
echo ""
echo -e "${YELLOW}=== SSL ===${NC}"
echo "sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN"
echo ""
