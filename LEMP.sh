#!/bin/bash

# Скрипт настройки LEMP для rextube.online
set -e

DOMAIN="rextube.online"
SITE_ROOT="/var/www/$DOMAIN"
DB_NAME="rextube_db"
DB_USER="rextube_user"
DB_PASS=$(openssl rand -base64 16)

echo "=== Начинаю настройку LEMP для $DOMAIN ==="

# 1. Обновление системы
echo "🔄 Обновляю систему..."
sudo apt update
sudo apt upgrade -y

# 2. Установка Nginx
if ! command -v nginx &> /dev/null; then
    echo "📦 Устанавливаю Nginx..."
    sudo apt install -y nginx
    sudo systemctl enable --now nginx
fi

# 3. Установка MariaDB
if ! command -v mariadb &> /dev/null; then
    echo "📦 Устанавливаю MariaDB..."
    sudo apt install -y mariadb-server mariadb-client
    sudo systemctl enable --now mariadb

    echo "🔒 Настраиваю безопасность MariaDB..."
    sudo mysql -e "DELETE FROM mysql.user WHERE User='';"
    sudo mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    sudo mysql -e "DROP DATABASE IF EXISTS test;"
    sudo mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    sudo mysql -e "FLUSH PRIVILEGES;"
fi

# 4. Установка PHP 8.4
if ! command -v php &> /dev/null || ! php -v | grep -q "8.4"; then
    echo "📦 Добавляю репозиторий PHP 8.4..."
    sudo apt install -y software-properties-common ca-certificates lsb-release
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt update

    echo "📦 Устанавливаю PHP 8.4 и расширения..."
    sudo apt install -y php8.4-fpm php8.4-mysql php8.4-cli php8.4-common \
        php8.4-curl php8.4-gd php8.4-mbstring php8.4-xml php8.4-zip \
        php8.4-bcmath php8.4-intl php8.4-soap php8.4-opcache
fi

# 5. Установка FFmpeg
if ! command -v ffmpeg &> /dev/null; then
    echo "📦 Устанавливаю FFmpeg..."
    sudo apt install -y ffmpeg
fi

# 6. Настройка БД
echo "🗄️ Создаю базу данных и пользователя..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# 7. Создание сайта
echo "📁 Создаю директорию сайта..."
sudo mkdir -p "$SITE_ROOT"
sudo chown -R "$USER":"$USER" "$SITE_ROOT"

cat > /tmp/index.php << EOF
<?php
echo "<h1>Добро пожаловать на $DOMAIN!</h1>";
echo "<p>PHP версия: " . phpversion() . "</p>";
echo "<p>FFmpeg: " . (shell_exec('which ffmpeg') ? '✓ Установлен' : '✗ Отсутствует') . "</p>";
try {
    \$pdo = new PDO('mysql:host=localhost;dbname=$DB_NAME', '$DB_USER', '$DB_PASS');
    echo "<p style='color:green;'>✅ Подключение к БД успешно</p>";
} catch (Exception \$e) {
    echo "<p style='color:red;'>❌ Ошибка БД: " . htmlspecialchars(\$e->getMessage()) . "</p>";
}
?>
EOF

sudo mv /tmp/index.php "$SITE_ROOT/"
sudo chown www-data:www-data "$SITE_ROOT/index.php"

# 8. Установка phpMyAdmin из PPA (обязательно для Ubuntu 22.04+)
echo "📦 Устанавливаю phpMyAdmin из официального PPA..."
sudo add-apt-repository -y ppa:phpmyadmin/ppa
sudo apt update
export DEBIAN_FRONTEND=noninteractive
sudo debconf-set-selections <<< "phpmyadmin phpmyadmin/reconfigure-webserver multiselect nginx"
sudo debconf-set-selections <<< "phpmyadmin phpmyadmin/dbconfig-install boolean true"
sudo debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/admin-user string root"
sudo debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/admin-pass password"
sudo debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/app-pass password $DB_PASS"
sudo debconf-set-selections <<< "phpmyadmin phpmyadmin/app-password-confirm password $DB_PASS"
sudo apt install -y phpmyadmin

# Проверка установки
if [ ! -d "/usr/share/phpmyadmin" ]; then
    echo "❌ Критическая ошибка: /usr/share/phpmyadmin не существует!"
    exit 1
fi

# 9. Конфигурация Nginx
PHP_SOCKET="/run/php/php8.4-fpm.sock"

# Запуск PHP-FPM
sudo systemctl enable --now php8.4-fpm

# Удаление default site
sudo rm -f /etc/nginx/sites-enabled/default

# Создание конфига
sudo tee /etc/nginx/sites-available/$DOMAIN > /dev/null << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name $DOMAIN www.$DOMAIN;

    root $SITE_ROOT;
    index index.php;

    client_max_body_size 256M;
    client_body_timeout 60s;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/rss+xml;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCKET;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }

    # phpMyAdmin — доступен по любому хосту (включая IP)
    location /phpmyadmin {
        alias /usr/share/phpmyadmin;
        index index.php;

        location ~ ^/phpmyadmin/(.+\.php)$ {
            alias /usr/share/phpmyadmin;
            fastcgi_pass unix:$PHP_SOCKET;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$request_filename;
            include fastcgi_params;
            fastcgi_param PHP_VALUE "upload_max_filesize=256M \n post_max_size=256M \n max_execution_time=300 \n max_input_time=300";
        }

        location ~* \.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|svg)$ {
            alias /usr/share/phpmyadmin;
            expires 30d;
            access_log off;
        }
    }

    location /phpMyAdmin {
        return 301 /phpmyadmin;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/

# 10. Настройка PHP
sudo mkdir -p /etc/php/8.4/fpm/conf.d/
sudo tee /etc/php/8.4/fpm/conf.d/upload.ini > /dev/null << 'EOF'
upload_max_filesize = 256M
post_max_size = 256M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
EOF

# 11. Настройка blowfish_secret для phpMyAdmin
BLOWFISH=$(openssl rand -base64 32)
sudo tee /etc/phpmyadmin/config.inc.php > /dev/null << EOF
<?php
\$cfg['blowfish_secret'] = '$BLOWFISH';
\$i = 0;
\$i++;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = 'localhost';
\$cfg['Servers'][\$i]['connect_type'] = 'tcp';
\$cfg['Servers'][\$i]['compress'] = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = false;
\$cfg['UploadDir'] = '';
\$cfg['SaveDir'] = '';
\$cfg['MaxRows'] = 100;
\$cfg['SendErrorReports'] = 'never';
?>
EOF

# 12. Перезагрузка сервисов
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.4-fpm

# 13. Установка Certbot и получение SSL
echo "🔐 Устанавливаю Certbot и получаю SSL..."
sudo apt install -y certbot python3-certbot-nginx

# Certbot может перезаписать конфиг — поэтому после него **восстанавливаем** /phpmyadmin, если нужно
if sudo certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email; then
    echo "✅ SSL успешно настроен!"
else
    echo "⚠️ SSL не получен (возможно, DNS не готов). Продолжаю без SSL."
fi

# ВАЖНО: Certbot мог удалить location /phpmyadmin — проверим и восстановим
if ! sudo grep -q "location /phpmyadmin" /etc/nginx/sites-enabled/$DOMAIN; then
    echo "🔧 Восстанавливаю /phpmyadmin в Nginx после Certbot..."
    sudo sed -i '/^    location ~ \\\.php\\\$/i \
    # phpMyAdmin\n\
    location /phpmyadmin {\n\
        alias /usr/share/phpmyadmin;\n\
        index index.php;\n\
        \n\
        location ~ ^/phpmyadmin/(.+\\.php)\$ {\n\
            alias /usr/share/phpmyadmin;\n\
            fastcgi_pass unix:/run/php/php8.4-fpm.sock;\n\
            fastcgi_index index.php;\n\
            fastcgi_param SCRIPT_FILENAME \$request_filename;\n\
            include fastcgi_params;\n\
            fastcgi_param PHP_VALUE "upload_max_filesize=256M \\n post_max_size=256M \\n max_execution_time=300 \\n max_input_time=300";\n\
        }\n\
        \n\
        location ~* \\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|svg)\$ {\n\
            alias /usr/share/phpmyadmin;\n\
            expires 30d;\n\
            access_log off;\n\
        }\n\
    }\n' /etc/nginx/sites-enabled/$DOMAIN

    sudo nginx -t && sudo systemctl reload nginx
fi

# 14. Финальная информация
SERVER_IP=$(curl -s ifconfig.me || echo "IP не определён")

echo ""
echo "✅ Настройка завершена!"
echo "🌐 Сайт: https://$DOMAIN"
echo "🔧 phpMyAdmin: http://$SERVER_IP/phpmyadmin"
echo "📂 Корень сайта: $SITE_ROOT"
echo ""
echo "=== Данные БД ==="
echo "Имя БД:     $DB_NAME"
echo "Пользователь: $DB_USER"
echo "Пароль:     $DB_PASS"
echo ""
echo "💡 Совет: ограничьте доступ к /phpmyadmin по IP в продакшене!"