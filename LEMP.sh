#!/bin/bash

# =============================================================================
# LEMP Stack Setup Script
# Hostname сервера: control.gmnode.ru (93.183.71.104)
# Сайт: rextube.online
# phpMyAdmin: control.gmnode.ru/phpmyadmin
# =============================================================================
set -e

# === КОНФИГУРАЦИЯ ===
HOSTNAME="control.gmnode.ru"
SERVER_IP="93.183.71.104"

DOMAIN="rextube.online"
SITE_ROOT="/var/www/$DOMAIN"

# БД уже существует с этими данными
DB_NAME="rextube"
DB_USER="almiron"
DB_PASS="Mtn999Un86@"

REPO_URL="https://github.com/AlmiroN-code/TuboCMS.git"

# Разрешить Composer работать от root
export COMPOSER_ALLOW_SUPERUSER=1

# Цвета для вывода
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
echo "  LEMP Stack Setup"
echo "  Server: $HOSTNAME ($SERVER_IP)"
echo "  Site: $DOMAIN"
echo "=============================================="
echo ""

# === 1. Проверка root ===
if [[ $EUID -ne 0 ]]; then
   log_error "Скрипт должен запускаться от root (sudo)"
   exit 1
fi

# === 2. Установка hostname ===
log_info "Устанавливаю hostname: $HOSTNAME"
hostnamectl set-hostname "$HOSTNAME"
grep -q "$HOSTNAME" /etc/hosts || echo "$SERVER_IP $HOSTNAME" >> /etc/hosts
log_success "Hostname установлен"

# === 3. Обновление системы ===
log_info "Обновляю систему..."
apt update && apt upgrade -y
apt install -y curl wget gnupg2 software-properties-common ca-certificates lsb-release apt-transport-https
log_success "Система обновлена"

# === 4. Установка Nginx ===
if ! command -v nginx &> /dev/null; then
    log_info "Устанавливаю Nginx..."
    apt install -y nginx
    systemctl enable --now nginx
    log_success "Nginx установлен"
else
    log_warn "Nginx уже установлен"
fi

# === 5. Установка MariaDB ===
if ! command -v mariadb &> /dev/null; then
    log_info "Устанавливаю MariaDB..."
    apt install -y mariadb-server mariadb-client
    systemctl enable --now mariadb
    log_success "MariaDB установлен"
else
    log_warn "MariaDB уже установлен"
fi

# === 6. Установка PHP 8.4 ===
if ! command -v php &> /dev/null || ! php -v | grep -q "8.4"; then
    log_info "Добавляю репозиторий PHP 8.4..."
    add-apt-repository -y ppa:ondrej/php
    apt update

    log_info "Устанавливаю PHP 8.4 и расширения..."
    apt install -y php8.4-fpm php8.4-mysql php8.4-cli php8.4-common \
        php8.4-curl php8.4-gd php8.4-mbstring php8.4-xml php8.4-zip \
        php8.4-bcmath php8.4-intl php8.4-soap php8.4-opcache php8.4-redis \
        php8.4-imagick php8.4-readline
    
    systemctl enable --now php8.4-fpm
    log_success "PHP 8.4 установлен"
else
    log_warn "PHP 8.4 уже установлен"
fi

# === 7. Установка FFmpeg ===
if ! command -v ffmpeg &> /dev/null; then
    log_info "Устанавливаю FFmpeg..."
    apt install -y ffmpeg
    log_success "FFmpeg установлен"
else
    log_warn "FFmpeg уже установлен"
fi

# === 8. Установка утилит ===
log_info "Устанавливаю дополнительные утилиты..."
apt install -y git unzip htop fail2ban ufw
log_success "Утилиты установлены"

# === 8.1. Установка Composer ===
if ! command -v composer &> /dev/null; then
    log_info "Устанавливаю Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    log_success "Composer установлен"
else
    log_warn "Composer уже установлен"
fi

# === 8.2. Установка Node.js ===
if ! command -v node &> /dev/null; then
    log_info "Устанавливаю Node.js 20 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
    log_success "Node.js установлен"
else
    log_warn "Node.js уже установлен"
fi

# === 9. Настройка БД ===
if ! mysql -e "SELECT 1 FROM mysql.user WHERE user='$DB_USER'" 2>/dev/null | grep -q 1; then
    log_info "Создаю базу данных $DB_NAME и пользователя $DB_USER..."
    mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    log_success "База данных создана"
else
    log_warn "БД и пользователь уже существуют - пропускаю"
fi

# === 10. Клонирование и развёртывание TuboCMS ===
log_info "Клонирую TuboCMS из GitHub..."
if [ -d "$SITE_ROOT" ]; then
    rm -rf "$SITE_ROOT"
fi

git clone "$REPO_URL" "$SITE_ROOT"
cd "$SITE_ROOT"
log_success "Репозиторий склонирован"

# Создание .env файла
log_info "Создаю .env.local файл..."
cat > "$SITE_ROOT/.env.local" << ENVEOF
APP_ENV=prod
APP_SECRET=$(openssl rand -hex 16)
APP_DEBUG=0

DATABASE_URL="mysql://$DB_USER:$DB_PASS@127.0.0.1:3306/$DB_NAME?serverVersion=10.11.0-MariaDB&charset=utf8mb4"

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

MAILER_DSN=null://null
ENVEOF
log_success ".env.local создан"

# Установка PHP зависимостей
log_info "Устанавливаю Composer зависимости..."
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
log_success "Composer зависимости установлены"

# Установка Node.js зависимостей и сборка фронтенда
log_info "Устанавливаю npm зависимости..."
npm ci
log_success "npm зависимости установлены"

log_info "Собираю фронтенд (production)..."
npm run build
log_success "Фронтенд собран"

# Миграции базы данных
log_info "Выполняю миграции БД..."
php bin/console doctrine:migrations:migrate --no-interaction
log_success "Миграции выполнены"

# Настройка Messenger транспорта
log_info "Настраиваю Messenger транспорт..."
php bin/console messenger:setup-transports
log_success "Messenger настроен"

# Очистка и прогрев кэша
log_info "Очищаю и прогреваю кэш..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
log_success "Кэш прогрет"

# Создание директорий для медиа
log_info "Создаю директории для медиа..."
mkdir -p "$SITE_ROOT/public/media/videos"
mkdir -p "$SITE_ROOT/public/media/posters"
mkdir -p "$SITE_ROOT/public/media/previews"
mkdir -p "$SITE_ROOT/public/media/avatars"
mkdir -p "$SITE_ROOT/public/media/site"
mkdir -p "$SITE_ROOT/var/log"
mkdir -p "$SITE_ROOT/var/cache"

# Права доступа
log_info "Настраиваю права доступа..."
chown -R www-data:www-data "$SITE_ROOT"
chmod -R 775 "$SITE_ROOT/var"
chmod -R 775 "$SITE_ROOT/public/media"

log_success "TuboCMS развёрнут"


# === 11. Установка phpMyAdmin ===
if [ ! -d "/usr/share/phpmyadmin" ]; then
    log_info "Устанавливаю phpMyAdmin..."
    add-apt-repository -y ppa:phpmyadmin/ppa
    apt update

    export DEBIAN_FRONTEND=noninteractive
    debconf-set-selections <<< "phpmyadmin phpmyadmin/reconfigure-webserver multiselect"
    debconf-set-selections <<< "phpmyadmin phpmyadmin/dbconfig-install boolean true"
    apt install -y phpmyadmin
    log_success "phpMyAdmin установлен"
else
    log_warn "phpMyAdmin уже установлен"
fi

# Конфигурация phpMyAdmin
log_info "Настраиваю phpMyAdmin..."
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
log_success "phpMyAdmin настроен"

# === 12. Конфигурация Nginx ===
log_info "Настраиваю Nginx..."

PHP_SOCKET="/run/php/php8.4-fpm.sock"
rm -f /etc/nginx/sites-enabled/default

# --- Конфиг для rextube.online ---
cat > /etc/nginx/sites-available/$DOMAIN << NGINXEOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    root $SITE_ROOT/public;
    index index.php index.html;

    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;

    client_max_body_size 2G;
    client_body_timeout 300s;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass unix:$PHP_SOCKET;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
    }

    location ~ /\. {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|svg|mp4|webm)\$ {
        expires 30d;
        access_log off;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/

# --- Конфиг для control.gmnode.ru (phpMyAdmin) ---
cat > /etc/nginx/sites-available/$HOSTNAME << NGINXEOF
server {
    listen 80;
    listen [::]:80;
    server_name $HOSTNAME $SERVER_IP;

    root /var/www/html;
    index index.html;

    access_log /var/log/nginx/${HOSTNAME}_access.log;
    error_log /var/log/nginx/${HOSTNAME}_error.log;

    location = / {
        default_type text/html;
        return 200 '<html><head><title>$HOSTNAME</title></head><body><h1>Server Control Panel</h1><p><a href="/phpmyadmin">phpMyAdmin</a></p></body></html>';
    }

    location /phpmyadmin {
        alias /usr/share/phpmyadmin;
        index index.php;

        location ~ ^/phpmyadmin/(.+\.php)\$ {
            alias /usr/share/phpmyadmin/\$1;
            fastcgi_pass unix:$PHP_SOCKET;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME /usr/share/phpmyadmin/\$1;
            include fastcgi_params;
            fastcgi_param PHP_VALUE "upload_max_filesize=256M
post_max_size=256M
max_execution_time=300
memory_limit=256M";
        }

        location ~* ^/phpmyadmin/(.+\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|svg|ttf|eot))\$ {
            alias /usr/share/phpmyadmin/\$1;
            expires 30d;
            access_log off;
        }
    }

    location /phpMyAdmin {
        return 301 /phpmyadmin;
    }

    location ~ /\. {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/$HOSTNAME /etc/nginx/sites-enabled/

nginx -t
log_success "Nginx настроен"

# === 13. Настройка PHP-FPM ===
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
expose_php = Off
display_errors = Off
log_errors = On
session.cookie_httponly = 1
PHPINI

log_success "PHP-FPM настроен"

# === 14. Firewall ===
log_info "Настраиваю Firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 'Nginx Full'
ufw --force enable
log_success "Firewall настроен"

# === 15. Fail2Ban ===
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

# === 16. Перезапуск сервисов ===
log_info "Перезапускаю сервисы..."
systemctl restart php8.4-fpm
systemctl restart nginx
systemctl restart mariadb
log_success "Сервисы перезапущены"

# === 17. Certbot ===
if ! command -v certbot &> /dev/null; then
    log_info "Устанавливаю Certbot..."
    apt install -y certbot python3-certbot-nginx
    log_success "Certbot установлен"
else
    log_warn "Certbot уже установлен"
fi

# === 18. Systemd сервис для Messenger Worker ===
log_info "Создаю systemd сервис для Messenger..."

cat > /etc/systemd/system/rextube-messenger.service << 'SVCEOF'
[Unit]
Description=RexTube Messenger Worker
After=network.target mariadb.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/rextube.online
ExecStart=/usr/bin/php /var/www/rextube.online/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVCEOF

systemctl daemon-reload
systemctl enable rextube-messenger
systemctl start rextube-messenger
log_success "Messenger Worker запущен"

# === 19. Сохранение учётных данных ===
CREDENTIALS_FILE="/root/.server_credentials"

cat > "$CREDENTIALS_FILE" << CREDEOF
============================================
  Server: $HOSTNAME ($SERVER_IP)
  Created: $(date)
============================================

REXTUBE DATABASE:
  DB: $DB_NAME
  User: $DB_USER
  Password: $DB_PASS

URLS:
  Site: http://$DOMAIN
  phpMyAdmin: http://$HOSTNAME/phpmyadmin
              http://$SERVER_IP/phpmyadmin

PATHS:
  Site root: $SITE_ROOT/public
  Logs: /var/log/nginx/

SSL (после настройки DNS):
  sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN
  sudo certbot --nginx -d $HOSTNAME
============================================
CREDEOF

chmod 600 "$CREDENTIALS_FILE"

# === ФИНАЛ ===
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  ✅ УСТАНОВКА ЗАВЕРШЕНА!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "🖥️  ${BLUE}Сервер:${NC}      $HOSTNAME ($SERVER_IP)"
echo ""
echo -e "🌐 ${BLUE}Сайт:${NC}        http://$DOMAIN"
echo -e "🔧 ${BLUE}phpMyAdmin:${NC}  http://$HOSTNAME/phpmyadmin"
echo -e "                http://$SERVER_IP/phpmyadmin"
echo ""
echo -e "${YELLOW}=== База данных ===${NC}"
echo -e "БД:           $DB_NAME"
echo -e "Пользователь: $DB_USER"
echo -e "Пароль:       $DB_PASS"
echo ""
echo -e "📄 Данные сохранены: ${BLUE}$CREDENTIALS_FILE${NC}"
echo ""
echo -e "${YELLOW}=== SSL (после DNS) ===${NC}"
echo "sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN"
echo "sudo certbot --nginx -d $HOSTNAME"
echo ""
echo -e "${YELLOW}=== Управление Messenger ===${NC}"
echo "systemctl status rextube-messenger"
echo "journalctl -u rextube-messenger -f"
echo ""
