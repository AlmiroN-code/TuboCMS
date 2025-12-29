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
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

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
apt install -y curl wget gnupg2 software-properties-common ca-certificates \
    lsb-release apt-transport-https git unzip htop fail2ban ufw
log_success "Система обновлена"

# === 4. Nginx ===
if ! command -v nginx &> /dev/null; then
    log_info "Устанавливаю Nginx..."
    apt install -y nginx
    systemctl enable --now nginx
    log_success "Nginx установлен"
else
    log_warn "Nginx уже установлен"
fi

# === 5. MariaDB ===
if ! command -v mariadb &> /dev/null; then
    log_info "Устанавливаю MariaDB..."
    apt install -y mariadb-server mariadb-client
    systemctl enable --now mariadb
    log_success "MariaDB установлен"
else
    log_warn "MariaDB уже установлен"
fi

# === 6. PHP 8.4 + все расширения для Symfony 8 ===
if ! command -v php8.4 &> /dev/null; then
    log_info "Добавляю репозиторий PHP 8.4..."
    add-apt-repository -y ppa:ondrej/php
    apt update

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
    
    systemctl enable --now php8.4-fpm
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
    sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    sudo mysql -e "FLUSH PRIVILEGES;"
    log_success "БД создана"
else
    log_warn "БД уже существует"
fi

# === 14. Клонирование TuboCMS ===
log_info "Клонирую TuboCMS..."
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
# symfony/process нужен для обработки видео (FFmpeg)
composer require symfony/process --no-interaction
log_success "Composer установлен"

# === 17. NPM зависимости ===
log_info "Устанавливаю npm зависимости..."
npm ci
log_success "npm установлен"

log_info "Собираю фронтенд..."
npm run build
log_success "Фронтенд собран"

# === 18. Миграции БД ===
log_info "Выполняю миграции..."
php bin/console doctrine:migrations:migrate --no-interaction 2>&1 | tee /tmp/migration.log || true

if grep -q "error" /tmp/migration.log; then
    log_warn "Пропускаю проблемные миграции..."
    php bin/console doctrine:migrations:version --add --all --no-interaction 2>/dev/null || true
fi

# === 19. Добавляем недостающие колонки в user ===
log_info "Проверяю структуру таблицы user..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS country VARCHAR(50) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS gender VARCHAR(20) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS orientation VARCHAR(20) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS marital_status VARCHAR(20) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS education VARCHAR(200) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS website VARCHAR(255) DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE user ADD COLUMN IF NOT EXISTS birth_date DATE DEFAULT NULL;" 2>/dev/null || true
log_success "Структура user обновлена"

# === 20. Добавляем likes_count и dislikes_count в video ===
log_info "Проверяю структуру таблицы video..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE video ADD COLUMN IF NOT EXISTS likes_count INT NOT NULL DEFAULT 0;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE video ADD COLUMN IF NOT EXISTS dislikes_count INT NOT NULL DEFAULT 0;" 2>/dev/null || true
log_success "Структура video обновлена"

# === 21. Создаём таблицу video_like если не существует ===
log_info "Проверяю таблицу video_like..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
CREATE TABLE IF NOT EXISTS video_like (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    type VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_ABF41D6FA76ED395 (user_id),
    INDEX IDX_ABF41D6F29C1004E (video_id),
    UNIQUE INDEX unique_user_video_like (user_id, video_id),
    PRIMARY KEY (id),
    CONSTRAINT FK_ABF41D6FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    CONSTRAINT FK_ABF41D6F29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQLEOF
log_success "Таблица video_like готова"

# === 22. Создаём таблицы role и permission ===
log_info "Проверяю таблицы ролей и разрешений..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
CREATE TABLE IF NOT EXISTS permission (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    category VARCHAR(50) NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX UNIQ_E04992AA5E237E06 (name),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS role (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX UNIQ_57698A6A5E237E06 (name),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS role_permission (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    INDEX IDX_6F7DF886D60322AC (role_id),
    INDEX IDX_6F7DF886FED90CCA (permission_id),
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT FK_6F7DF886D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE,
    CONSTRAINT FK_6F7DF886FED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS user_role (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    INDEX IDX_2DE8C6A3A76ED395 (user_id),
    INDEX IDX_2DE8C6A3D60322AC (role_id),
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQLEOF
log_success "Таблицы ролей готовы"

# === 23. Создаём таблицу storage ===
log_info "Проверяю таблицу storage..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
CREATE TABLE IF NOT EXISTS storage (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL,
    config JSON NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(id),
    INDEX idx_storage_default (is_default),
    INDEX idx_storage_type (type),
    INDEX idx_storage_enabled (is_enabled)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQLEOF
log_success "Таблица storage готова"

# === 24. Добавляем колонки storage в video_file ===
log_info "Проверяю структуру video_file..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE video_file ADD COLUMN IF NOT EXISTS storage_id INT DEFAULT NULL;" 2>/dev/null || true
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "ALTER TABLE video_file ADD COLUMN IF NOT EXISTS remote_path VARCHAR(500) DEFAULT NULL;" 2>/dev/null || true
log_success "Структура video_file обновлена"

# === 25. Создаём таблицы для моделей ===
log_info "Проверяю таблицы моделей..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
CREATE TABLE IF NOT EXISTS model_profile (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT DEFAULT NULL,
    display_name VARCHAR(100) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    bio LONGTEXT DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    cover_photo VARCHAR(255) DEFAULT NULL,
    gender VARCHAR(10) NOT NULL DEFAULT 'female',
    age INT DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    ethnicity VARCHAR(100) DEFAULT NULL,
    career_start DATE DEFAULT NULL,
    hair_color VARCHAR(20) DEFAULT NULL,
    eye_color VARCHAR(20) DEFAULT NULL,
    has_tattoos TINYINT(1) NOT NULL DEFAULT 0,
    has_piercings TINYINT(1) NOT NULL DEFAULT 0,
    breast_size VARCHAR(20) DEFAULT NULL,
    height INT DEFAULT NULL,
    weight INT DEFAULT NULL,
    views_count INT NOT NULL DEFAULT 0,
    subscribers_count INT NOT NULL DEFAULT 0,
    videos_count INT NOT NULL DEFAULT 0,
    likes_count INT NOT NULL DEFAULT 0,
    dislikes_count INT NOT NULL DEFAULT 0,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX UNIQ_model_slug (slug),
    INDEX IDX_model_user (user_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_model_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS video_model (
    video_id INT NOT NULL,
    model_profile_id INT NOT NULL,
    INDEX IDX_video_model_video (video_id),
    INDEX IDX_video_model_model (model_profile_id),
    PRIMARY KEY (video_id, model_profile_id),
    CONSTRAINT FK_video_model_video FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    CONSTRAINT FK_video_model_model FOREIGN KEY (model_profile_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS model_subscription (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    model_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_model_sub_user (user_id),
    INDEX IDX_model_sub_model (model_id),
    UNIQUE INDEX unique_model_subscription (user_id, model_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_model_sub_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    CONSTRAINT FK_model_sub_model FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS model_like (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    model_id INT NOT NULL,
    type VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_model_like_user (user_id),
    INDEX IDX_model_like_model (model_id),
    UNIQUE INDEX unique_model_like (user_id, model_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_model_like_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    CONSTRAINT FK_model_like_model FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQLEOF
log_success "Таблицы моделей готовы"

# === 26. Создаём таблицу video_encoding_profile ===
log_info "Проверяю таблицу video_encoding_profile..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
CREATE TABLE IF NOT EXISTS video_encoding_profile (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(50) NOT NULL,
    resolution VARCHAR(20) NOT NULL,
    bitrate INT NOT NULL,
    codec VARCHAR(10) NOT NULL DEFAULT 'libx264',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    order_position INT NOT NULL DEFAULT 0,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

INSERT IGNORE INTO video_encoding_profile (name, resolution, bitrate, codec, is_active, order_position) VALUES
    ('360p', '640x360', 800, 'libx264', 1, 1),
    ('480p', '854x480', 1200, 'libx264', 1, 2),
    ('720p', '1280x720', 2500, 'libx264', 1, 3),
    ('1080p', '1920x1080', 5000, 'libx264', 1, 4);
SQLEOF
log_success "Профили кодирования готовы"

# === 26. Создание админа ===
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

log_success "Админ создан: $ADMIN_EMAIL / $ADMIN_PASSWORD"

# === 27. Инициализация ролей и разрешений ===
log_info "Инициализирую роли и разрешения..."
php bin/console app:init-roles-permissions 2>/dev/null || true
log_success "Роли и разрешения инициализированы"

# === 28. Messenger ===
log_info "Настраиваю Messenger..."
php bin/console messenger:setup-transports 2>/dev/null || true
log_success "Messenger настроен"

# === 29. Кэш ===
log_info "Прогреваю кэш..."
php bin/console doctrine:cache:clear-metadata 2>/dev/null || true
php bin/console doctrine:cache:clear-query 2>/dev/null || true
rm -rf var/cache/*
mkdir -p var/cache/prod
chown -R www-data:www-data var/
sudo -u www-data php bin/console cache:warmup --env=prod
log_success "Кэш прогрет"

# === 30. Права ===
log_info "Настраиваю права..."
mkdir -p "$SITE_ROOT/public/media/videos"
mkdir -p "$SITE_ROOT/public/media/posters"
mkdir -p "$SITE_ROOT/public/media/previews"
mkdir -p "$SITE_ROOT/public/media/avatars"
mkdir -p "$SITE_ROOT/public/media/site"
mkdir -p "$SITE_ROOT/public/media/covers"
chown -R www-data:www-data "$SITE_ROOT"
chmod -R 775 "$SITE_ROOT/var"
chmod -R 775 "$SITE_ROOT/public/media"
log_success "Права настроены"

# === 31. phpMyAdmin ===
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

# === 32. Nginx конфигурация ===
log_info "Настраиваю Nginx..."
PHP_SOCKET="/run/php/php8.4-fpm.sock"
rm -f /etc/nginx/sites-enabled/default

cat > /etc/nginx/sites-available/$DOMAIN << 'NGINXEOF'
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

    gzip on;
    gzip_vary on;
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

nginx -t
log_success "Nginx настроен"

# === 33. PHP-FPM конфигурация ===
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

# === 34. Firewall ===
log_info "Настраиваю Firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 'Nginx Full'
ufw --force enable
log_success "Firewall настроен"

# === 35. Fail2Ban ===
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
log_success "Fail2Ban настроен"

# === 36. Certbot ===
if ! command -v certbot &> /dev/null; then
    log_info "Устанавливаю Certbot..."
    apt install -y certbot python3-certbot-nginx
    log_success "Certbot установлен"
fi

# === 36.1. Получение SSL сертификата ===
log_info "Получаю SSL сертификат для $DOMAIN..."
certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email $ADMIN_EMAIL --redirect 2>/dev/null || log_warn "SSL не удалось получить. Проверь DNS записи и запусти вручную: certbot --nginx -d $DOMAIN -d www.$DOMAIN"

# === 37. Messenger Worker ===
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
systemctl enable seexxx-messenger
systemctl start seexxx-messenger
log_success "Messenger Worker запущен"

# === 38. Перезапуск сервисов ===
log_info "Перезапускаю сервисы..."
systemctl restart php8.4-fpm
systemctl restart nginx
log_success "Сервисы перезапущены"

# === 39. Сохранение данных ===
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
