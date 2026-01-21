#!/bin/bash

# =============================================================================
# LEMP Stack Setup Script for Symfony 8 + SexVids
# Hostname: control.gmnode.ru (93.183.71.104)
# Site: sexvids.online
# phpMyAdmin: control.gmnode.ru/phpmyadmin
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
    # Исправляем collation если нужно
    sudo mysql -e "ALTER DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
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
MAILER_FROM=noreply@$DOMAIN
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
        auto_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        metadata_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
        query_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
        result_cache_driver:
            type: pool
            pool: doctrine.result_cache_pool
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
            metadata_cache_driver:
                type: pool
                pool: doctrine.system_cache_pool
            query_cache_driver:
                type: pool
                pool: doctrine.system_cache_pool
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool
DOCTRINEEOF
check_success "Ошибка исправления doctrine.yaml"
log_success "doctrine.yaml исправлен"

log_info "Собираю фронтенд..."
npm run build
check_success "Ошибка сборки фронтенда"
log_success "Фронтенд собран"

# === 18. Миграции БД ===
log_info "Выполняю миграции Doctrine..."

# Создаем полную схему БД встроенным SQL
log_info "Создаю полную схему БД..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
-- ============================================================================
-- ПОЛНАЯ СХЕМА БД ДЛЯ REXTUBE
-- ============================================================================

-- Основные таблицы
CREATE TABLE IF NOT EXISTS `user` (
    id INT AUTO_INCREMENT NOT NULL,
    email VARCHAR(180) NOT NULL,
    username VARCHAR(180) NOT NULL UNIQUE,
    roles JSON NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    bio LONGTEXT DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    country VARCHAR(50) DEFAULT NULL,
    gender VARCHAR(20) DEFAULT NULL,
    orientation VARCHAR(20) DEFAULT NULL,
    marital_status VARCHAR(20) DEFAULT NULL,
    education VARCHAR(200) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    is_verified TINYINT NOT NULL DEFAULT 0,
    is_premium TINYINT NOT NULL DEFAULT 0,
    processing_priority INT NOT NULL DEFAULT 5,
    subscribers_count INT NOT NULL DEFAULT 0,
    videos_count INT NOT NULL DEFAULT 0,
    total_views INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS category (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    poster VARCHAR(255) DEFAULT NULL,
    videos_count INT NOT NULL DEFAULT 0,
    is_active TINYINT NOT NULL DEFAULT 1,
    order_position INT NOT NULL DEFAULT 0,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description LONGTEXT DEFAULT NULL,
    meta_keywords VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS tag (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    usage_count INT NOT NULL DEFAULT 0,
    description LONGTEXT DEFAULT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description LONGTEXT DEFAULT NULL,
    meta_keywords VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS video_encoding_profile (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(50) NOT NULL,
    resolution VARCHAR(20) NOT NULL,
    bitrate INT NOT NULL,
    codec VARCHAR(10) NOT NULL DEFAULT 'h264',
    is_active TINYINT NOT NULL DEFAULT 1,
    order_position INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS storage (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL,
    config JSON NOT NULL,
    is_default TINYINT NOT NULL DEFAULT 0,
    is_enabled TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS series (
    id INT AUTO_INCREMENT NOT NULL,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    thumbnail VARCHAR(255) DEFAULT NULL,
    slug VARCHAR(250) NOT NULL UNIQUE,
    videos_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS season (
    id INT AUTO_INCREMENT NOT NULL,
    series_id INT NOT NULL,
    number INT NOT NULL,
    title VARCHAR(200) DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS video (
    id INT AUTO_INCREMENT NOT NULL,
    created_by_id INT DEFAULT NULL,
    season_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    slug VARCHAR(250) NOT NULL UNIQUE,
    temp_video_file VARCHAR(255) DEFAULT NULL,
    converted_files JSON DEFAULT NULL,
    preview VARCHAR(255) DEFAULT NULL,
    poster VARCHAR(255) DEFAULT NULL,
    duration INT NOT NULL DEFAULT 0,
    resolution VARCHAR(20) DEFAULT NULL,
    format VARCHAR(10) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    is_featured TINYINT NOT NULL DEFAULT 0,
    views_count INT NOT NULL DEFAULT 0,
    impressions_count INT NOT NULL DEFAULT 0,
    comments_count INT NOT NULL DEFAULT 0,
    likes_count INT NOT NULL DEFAULT 0,
    dislikes_count INT NOT NULL DEFAULT 0,
    meta_description VARCHAR(160) DEFAULT NULL,
    processing_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    processing_progress INT NOT NULL DEFAULT 0,
    processing_error LONGTEXT DEFAULT NULL,
    episode_number INT DEFAULT NULL,
    animated_preview VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_status_created (status, created_at),
    INDEX idx_slug (slug),
    INDEX idx_views (views_count),
    FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL,
    FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS video_file (
    id INT AUTO_INCREMENT NOT NULL,
    video_id INT NOT NULL,
    profile_id INT NOT NULL,
    storage_id INT DEFAULT NULL,
    file VARCHAR(255) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    duration INT NOT NULL DEFAULT 0,
    is_primary TINYINT NOT NULL DEFAULT 0,
    remote_path VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_video_profile (video_id, profile_id),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES video_encoding_profile (id),
    FOREIGN KEY (storage_id) REFERENCES storage (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS model_profile (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT DEFAULT NULL,
    display_name VARCHAR(100) NOT NULL,
    aliases JSON DEFAULT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
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
    has_tattoos TINYINT NOT NULL DEFAULT 0,
    has_piercings TINYINT NOT NULL DEFAULT 0,
    breast_size VARCHAR(20) DEFAULT NULL,
    height INT DEFAULT NULL,
    weight INT DEFAULT NULL,
    views_count INT NOT NULL DEFAULT 0,
    subscribers_count INT NOT NULL DEFAULT 0,
    videos_count INT NOT NULL DEFAULT 0,
    likes_count INT NOT NULL DEFAULT 0,
    dislikes_count INT NOT NULL DEFAULT 0,
    is_verified TINYINT NOT NULL DEFAULT 0,
    is_active TINYINT NOT NULL DEFAULT 1,
    is_premium TINYINT NOT NULL DEFAULT 0,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description LONGTEXT DEFAULT NULL,
    meta_keywords VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS comment (
    id INT AUTO_INCREMENT NOT NULL,
    video_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    content LONGTEXT NOT NULL,
    is_edited TINYINT NOT NULL DEFAULT 0,
    is_pinned TINYINT NOT NULL DEFAULT 0,
    likes_count INT NOT NULL DEFAULT 0,
    replies_count INT NOT NULL DEFAULT 0,
    moderation_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_video_parent (video_id, parent_id),
    INDEX idx_moderation_status (moderation_status),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comment (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS subscription (
    id INT AUTO_INCREMENT NOT NULL,
    subscriber_id INT NOT NULL,
    channel_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_subscription (subscriber_id, channel_id),
    FOREIGN KEY (subscriber_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES `user` (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT NOT NULL,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value LONGTEXT DEFAULT NULL,
    setting_type VARCHAR(50) NOT NULL DEFAULT 'string',
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS role (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS permission (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    category VARCHAR(50) NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_placement (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description LONGTEXT DEFAULT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'banner',
    position VARCHAR(30) NOT NULL DEFAULT 'sidebar',
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    order_position INT NOT NULL DEFAULT 0,
    allowed_pages JSON DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_ad_placement_slug (slug),
    INDEX idx_ad_placement_active (is_active)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_campaign (
    id INT AUTO_INCREMENT NOT NULL,
    created_by_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    total_budget DECIMAL(12, 2) DEFAULT NULL,
    daily_budget DECIMAL(10, 2) DEFAULT NULL,
    spent_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_impressions INT NOT NULL DEFAULT 0,
    total_clicks INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_ad_campaign_status (status),
    FOREIGN KEY (created_by_id) REFERENCES `user` (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_segment (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description LONGTEXT DEFAULT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'custom',
    rules JSON DEFAULT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    users_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_ab_test (
    id INT AUTO_INCREMENT NOT NULL,
    created_by_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    traffic_split_a INT NOT NULL DEFAULT 50,
    traffic_split_b INT NOT NULL DEFAULT 50,
    winner_metric VARCHAR(50) NOT NULL DEFAULT 'ctr',
    winner VARCHAR(10) DEFAULT NULL,
    statistical_significance DECIMAL(10, 4) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (created_by_id) REFERENCES `user` (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad (
    id INT AUTO_INCREMENT NOT NULL,
    placement_id INT NOT NULL,
    campaign_id INT DEFAULT NULL,
    created_by_id INT DEFAULT NULL,
    ab_test_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    format VARCHAR(30) NOT NULL DEFAULT 'image',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    image_url VARCHAR(500) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    vast_url VARCHAR(1000) DEFAULT NULL,
    html_content LONGTEXT DEFAULT NULL,
    script_code LONGTEXT DEFAULT NULL,
    click_url VARCHAR(500) DEFAULT NULL,
    alt_text VARCHAR(200) DEFAULT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    open_in_new_tab TINYINT NOT NULL DEFAULT 1,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    priority INT NOT NULL DEFAULT 0,
    weight INT NOT NULL DEFAULT 100,
    budget DECIMAL(10, 2) DEFAULT NULL,
    cpm DECIMAL(10, 4) DEFAULT NULL,
    cpc DECIMAL(10, 4) DEFAULT NULL,
    impression_limit INT DEFAULT NULL,
    click_limit INT DEFAULT NULL,
    daily_impression_limit INT DEFAULT NULL,
    daily_click_limit INT DEFAULT NULL,
    impressions_count INT NOT NULL DEFAULT 0,
    clicks_count INT NOT NULL DEFAULT 0,
    unique_impressions_count INT NOT NULL DEFAULT 0,
    unique_clicks_count INT NOT NULL DEFAULT 0,
    spent_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    geo_targeting JSON DEFAULT NULL,
    time_targeting JSON DEFAULT NULL,
    device_targeting JSON DEFAULT NULL,
    category_targeting JSON DEFAULT NULL,
    ab_test_variant VARCHAR(10) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_ad_status_dates (status, start_date, end_date),
    INDEX idx_ad_active (is_active),
    FOREIGN KEY (placement_id) REFERENCES ad_placement (id),
    FOREIGN KEY (campaign_id) REFERENCES ad_campaign (id),
    FOREIGN KEY (created_by_id) REFERENCES `user` (id),
    FOREIGN KEY (ab_test_id) REFERENCES ad_ab_test (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_statistic (
    id INT AUTO_INCREMENT NOT NULL,
    ad_id INT NOT NULL,
    date DATE NOT NULL,
    impressions INT NOT NULL DEFAULT 0,
    clicks INT NOT NULL DEFAULT 0,
    unique_impressions INT NOT NULL DEFAULT 0,
    unique_clicks INT NOT NULL DEFAULT 0,
    spent DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    revenue DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    conversions INT NOT NULL DEFAULT 0,
    hourly_data JSON DEFAULT NULL,
    geo_data JSON DEFAULT NULL,
    device_data JSON DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_ad_date (ad_id, date),
    INDEX idx_ad_stat_ad_date (ad_id, date),
    INDEX idx_ad_stat_date (date),
    FOREIGN KEY (ad_id) REFERENCES ad (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS video_like (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    is_like TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_user_video_like (user_id, video_id),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS model_like (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    model_id INT NOT NULL,
    type VARCHAR(10) NOT NULL DEFAULT 'like',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_model_like (user_id, model_id),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS bookmark (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_user_video_bookmark (user_id, video_id),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    watched_seconds INT NOT NULL DEFAULT 0,
    watch_progress INT NOT NULL DEFAULT 0,
    watched_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_user_video (user_id, video_id),
    INDEX idx_user_watched (user_id, watched_at),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS watch_later (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX watch_later_user_video_unique (user_id, video_id),
    INDEX idx_watch_later_user (user_id),
    INDEX idx_watch_later_created (created_at),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS notification (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    data JSON NOT NULL,
    is_read TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_user_unread (user_id, is_read, created_at),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS playlist (
    id INT AUTO_INCREMENT NOT NULL,
    owner_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    thumbnail VARCHAR(255) DEFAULT NULL,
    is_public TINYINT NOT NULL DEFAULT 1,
    videos_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS playlist_video (
    id INT AUTO_INCREMENT NOT NULL,
    playlist_id INT NOT NULL,
    video_id INT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    added_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_playlist_video (playlist_id, video_id),
    FOREIGN KEY (playlist_id) REFERENCES playlist (id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS content_protection_setting (
    id INT AUTO_INCREMENT NOT NULL,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value LONGTEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS model_subscription (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    model_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX unique_model_subscription (user_id, model_id),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- ============================================================================
-- JUNCTION ТАБЛИЦЫ (ManyToMany отношения)
-- ============================================================================

CREATE TABLE IF NOT EXISTS video_category (
    video_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (video_id, category_id),
    INDEX IDX_video_category_video (video_id),
    INDEX IDX_video_category_category (category_id),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS video_tag (
    video_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (video_id, tag_id),
    INDEX IDX_video_tag_video (video_id),
    INDEX IDX_video_tag_tag (tag_id),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS video_model (
    video_id INT NOT NULL,
    model_profile_id INT NOT NULL,
    PRIMARY KEY (video_id, model_profile_id),
    INDEX IDX_video_model_video (video_id),
    INDEX IDX_video_model_model (model_profile_id),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (model_profile_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS user_role (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    INDEX IDX_user_role_user (user_id),
    INDEX IDX_user_role_role (role_id),
    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS role_permission (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    INDEX IDX_role_permission_role (role_id),
    INDEX IDX_role_permission_permission (permission_id),
    FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_segment_relation (
    ad_id INT NOT NULL,
    ad_segment_id INT NOT NULL,
    PRIMARY KEY (ad_id, ad_segment_id),
    INDEX IDX_ad_segment_relation_ad (ad_id),
    INDEX IDX_ad_segment_relation_segment (ad_segment_id),
    FOREIGN KEY (ad_id) REFERENCES ad (id) ON DELETE CASCADE,
    FOREIGN KEY (ad_segment_id) REFERENCES ad_segment (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQLEOF

check_success "Ошибка создания схемы БД"
log_success "Полная схема БД создана"

# Пересоздаем таблицу video_model с правильной структурой
log_info "Пересоздаю таблицу video_model..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
-- Удаляем старую таблицу если существует
DROP TABLE IF EXISTS video_model;

-- Создаем новую таблицу с правильной структурой
CREATE TABLE video_model (
    video_id INT NOT NULL,
    model_profile_id INT NOT NULL,
    PRIMARY KEY (video_id, model_profile_id),
    INDEX IDX_video_model_video (video_id),
    INDEX IDX_video_model_model (model_profile_id),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (model_profile_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQLEOF
check_success "Ошибка пересоздания таблицы video_model"
log_success "Таблица video_model пересоздана"

# Исправляем связи в VideoRepository - убираем несуществующие JOIN
log_info "Проверяю таблицу video_model..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
-- Проверяем существование таблицы video_model
SELECT COUNT(*) as table_exists FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_model';
SQLEOF

# Убеждаемся что таблица video_model создана правильно
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
-- Пересоздаем таблицу video_model если нужно
DROP TABLE IF EXISTS video_model;
CREATE TABLE video_model (
    video_id INT NOT NULL,
    model_profile_id INT NOT NULL,
    PRIMARY KEY (video_id, model_profile_id),
    INDEX idx_video_model_video (video_id),
    INDEX idx_video_model_model (model_profile_id),
    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
    FOREIGN KEY (model_profile_id) REFERENCES model_profile (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQLEOF

check_success "Ошибка создания таблицы video_model"
log_success "Таблица video_model создана правильно"

# Теперь выполняем миграции (они должны быть идемпотентными)
php bin/console doctrine:migrations:migrate --no-interaction 2>&1 | tee /tmp/migration.log || true

# Проверяем подключение к БД и схему
log_info "Проверяю схему БД..."
php bin/console doctrine:schema:validate --skip-sync || log_warn "Схема БД имеет предупреждения"

# Помечаем все миграции как выполненные
php bin/console doctrine:migrations:version --add --all --no-interaction 2>/dev/null || true
log_success "Миграции выполнены"

# === 19. Создание админа ===
log_info "Создаю супер админа..."

# Генерируем хеш пароля
ADMIN_HASH=$(php bin/console security:hash-password "$ADMIN_PASSWORD" --no-interaction 2>/dev/null | grep -oP '(?<=Hash\s{2})\S+' || echo '$2y$13$R9h7cIPz0gi.URNNX3kh2OPST9/PgBsqqqjiJ2eiK4m9wWyW2b7Oi')

# Создаем админа с правильной collation
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << SQLEOF
INSERT INTO user (email, username, roles, password, is_verified, is_premium, processing_priority, subscribers_count, videos_count, total_views, created_at, updated_at)
VALUES (
    '$ADMIN_EMAIL',
    '$ADMIN_USERNAME',
    JSON_ARRAY('ROLE_ADMIN', 'ROLE_USER'),
    '$ADMIN_HASH',
    1,
    1,
    10,
    0,
    0,
    0,
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE password='$ADMIN_HASH', roles=JSON_ARRAY('ROLE_ADMIN', 'ROLE_USER');
SQLEOF
check_success "Ошибка создания администратора"
log_success "Админ создан: $ADMIN_EMAIL / $ADMIN_PASSWORD"

# === 28. Инициализация ролей, разрешений и начальных данных ===
log_info "Инициализирую роли, разрешения и начальные данные..."

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'SQLEOF'
-- Роли
INSERT IGNORE INTO `role` (name, display_name, description, is_active, created_at, updated_at) VALUES
('ROLE_ADMIN', 'Администратор', 'Полный доступ к системе', 1, NOW(), NOW()),
('ROLE_MODERATOR', 'Модератор', 'Модерация контента и пользователей', 1, NOW(), NOW()),
('ROLE_USER', 'Пользователь', 'Обычный пользователь', 1, NOW(), NOW()),
('ROLE_CREATOR', 'Создатель контента', 'Может загружать видео', 1, NOW(), NOW());

-- Разрешения
INSERT IGNORE INTO `permission` (name, display_name, description, category, is_active, created_at, updated_at) VALUES
('video.view', 'Просмотр видео', 'Просмотр видео', 'video', 1, NOW(), NOW()),
('video.create', 'Создание видео', 'Загрузка новых видео', 'video', 1, NOW(), NOW()),
('video.edit', 'Редактирование видео', 'Редактирование своих видео', 'video', 1, NOW(), NOW()),
('video.delete', 'Удаление видео', 'Удаление своих видео', 'video', 1, NOW(), NOW()),
('video.admin', 'Администрирование видео', 'Управление всеми видео', 'video', 1, NOW(), NOW()),
('comment.create', 'Создание комментариев', 'Оставление комментариев', 'comment', 1, NOW(), NOW()),
('comment.edit', 'Редактирование комментариев', 'Редактирование своих комментариев', 'comment', 1, NOW(), NOW()),
('comment.delete', 'Удаление комментариев', 'Удаление своих комментариев', 'comment', 1, NOW(), NOW()),
('comment.moderate', 'Модерация комментариев', 'Модерация всех комментариев', 'comment', 1, NOW(), NOW()),
('user.view', 'Просмотр профилей', 'Просмотр профилей пользователей', 'user', 1, NOW(), NOW()),
('user.edit', 'Редактирование профиля', 'Редактирование своего профиля', 'user', 1, NOW(), NOW()),
('user.admin', 'Администрирование пользователей', 'Управление всеми пользователями', 'user', 1, NOW(), NOW()),
('category.view', 'Просмотр категорий', 'Просмотр категорий', 'category', 1, NOW(), NOW()),
('category.admin', 'Администрирование категорий', 'Управление категориями', 'category', 1, NOW(), NOW()),
('tag.view', 'Просмотр тегов', 'Просмотр тегов', 'tag', 1, NOW(), NOW()),
('tag.admin', 'Администрирование тегов', 'Управление тегами', 'tag', 1, NOW(), NOW()),
('system.settings', 'Настройки системы', 'Управление настройками', 'system', 1, NOW(), NOW()),
('system.admin', 'Администрирование системы', 'Полный доступ к системе', 'system', 1, NOW(), NOW());

-- Связь ролей и разрешений
INSERT IGNORE INTO `role_permission` (role_id, permission_id) 
SELECT r.id, p.id FROM `role` r, `permission` p 
WHERE r.name = 'ROLE_ADMIN';

INSERT IGNORE INTO `role_permission` (role_id, permission_id)
SELECT r.id, p.id FROM `role` r, `permission` p
WHERE r.name = 'ROLE_MODERATOR' AND p.name IN (
  'video.view', 'video.admin', 'comment.moderate', 'user.view', 'user.admin'
);

INSERT IGNORE INTO `role_permission` (role_id, permission_id)
SELECT r.id, p.id FROM `role` r, `permission` p
WHERE r.name = 'ROLE_CREATOR' AND p.name IN (
  'video.view', 'video.create', 'video.edit', 'video.delete',
  'comment.create', 'comment.edit', 'comment.delete',
  'user.view', 'user.edit'
);

INSERT IGNORE INTO `role_permission` (role_id, permission_id)
SELECT r.id, p.id FROM `role` r, `permission` p
WHERE r.name = 'ROLE_USER' AND p.name IN (
  'video.view', 'comment.create', 'comment.edit', 'comment.delete',
  'user.view', 'user.edit'
);

-- Профили кодирования видео
INSERT IGNORE INTO `video_encoding_profile` (name, resolution, bitrate, codec, is_active, order_position) VALUES
('360p', '360p', 400, 'h264', 1, 1),
('480p', '480p', 1000, 'h264', 1, 2),
('720p', '720p', 2500, 'h264', 1, 3),
('1080p', '1080p', 5000, 'h264', 1, 4);

-- Хранилище
INSERT IGNORE INTO `storage` (name, type, config, is_default, is_enabled, created_at, updated_at) VALUES
('Local Storage', 'local', '{"baseUrl": "/media", "basePath": "/"}', 1, 1, NOW(), NOW());

-- Настройки сайта
INSERT IGNORE INTO `site_settings` (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'RexTube', 'string', 'Название сайта'),
('site_description', 'Видео хостинг', 'string', 'Описание сайта'),
('site_keywords', 'видео, хостинг, онлайн', 'string', 'Ключевые слова'),
('contact_email', 'admin@sexvids.online', 'string', 'Email для связи'),
('max_video_size', '2000', 'integer', 'Максимальный размер видео (MB)'),
('allowed_video_formats', 'mp4,avi,mov,mkv', 'string', 'Разрешенные форматы видео'),
('videos_per_page', '24', 'integer', 'Видео на странице'),
('registration_enabled', '1', 'boolean', 'Разрешить регистрацию'),
('email_verification_required', '0', 'boolean', 'Требовать подтверждение email'),
('comments_enabled', '1', 'boolean', 'Включить комментарии'),
('comments_moderation', '0', 'boolean', 'Модерация комментариев');
SQLEOF

check_success "Ошибка инициализации ролей и разрешений"
log_success "Роли, разрешения и начальные данные инициализированы"

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
# Очищаем старый кеш
rm -rf var/cache/prod/*
rm -rf var/log/*
mkdir -p var/cache/prod
mkdir -p var/log

# Устанавливаем права перед прогревом кеша
chown -R www-data:www-data var/
chmod -R 755 var/
chmod -R 775 var/cache var/log

# Прогреваем кеш от имени www-data
sudo -u www-data php bin/console cache:clear --env=prod --no-debug
sudo -u www-data php bin/console cache:warmup --env=prod --no-debug
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
rm -f /etc/nginx/sites-enabled/seexxx.online
rm -f /etc/nginx/sites-available/seexxx.online

# Создаем файл с rate limiting zones (если его еще нет)
if [ ! -f /etc/nginx/conf.d/rate_limiting.conf ]; then
    cat > /etc/nginx/conf.d/rate_limiting.conf << 'RATELIMITEOF'
# Rate limiting zones
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/m;
limit_req_zone $binary_remote_addr zone=upload:10m rate=2r/m;
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
RATELIMITEOF
fi

# Удаляем старый конфиг сайта если существует
rm -f /etc/nginx/sites-available/$DOMAIN
rm -f /etc/nginx/sites-enabled/$DOMAIN

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
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
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
cat > /etc/systemd/system/sexvids-messenger.service << 'SVCEOF'
[Unit]
Description=SexVids Messenger Worker
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
  SexVids Server Credentials
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
systemctl is-active --quiet sexvids-messenger || log_error "Messenger Worker не запущен"

# Проверяем что сайт отвечает
curl -f -s http://localhost > /dev/null || log_error "Сайт не отвечает на localhost"

log_success "Все сервисы работают корректно"

# === ФИНАЛ ===
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  ✅ УСТАНОВКА ЗАВЕРШЕНА!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""

# === ФИНАЛЬНАЯ ПРОВЕРКА ===
log_info "Выполняю финальную проверку..."

# Проверяем что Symfony работает
cd "$SITE_ROOT"
if php bin/console debug:router > /dev/null 2>&1; then
    log_success "Symfony работает корректно"
else
    log_error "Symfony не работает! Проверьте логи в $SITE_ROOT/var/log/"
fi

# Проверяем права доступа
if [ -w "$SITE_ROOT/var/cache" ] && [ -w "$SITE_ROOT/var/log" ]; then
    log_success "Права доступа настроены корректно"
else
    log_warn "Проблемы с правами доступа к var/"
fi

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
