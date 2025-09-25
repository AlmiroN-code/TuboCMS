#!/bin/bash

# Скрипт развертывания Django+HTMX проекта для ручной загрузки файлов
# Использование: ./deploy.sh your-domain.com

set -e

DOMAIN=$1
if [ -z "$DOMAIN" ]; then
    echo "Использование: $0 your-domain.com"
    echo "Пример: $0 mysite.com"
    exit 1
fi

echo "🚀 Начинаем развертывание проекта на домене: $DOMAIN"
echo "📁 Файлы должны быть загружены в /var/www/$DOMAIN/"

# Функция проверки установки пакета
check_and_install_package() {
    local package=$1
    if dpkg -l | grep -q "^ii.*$package "; then
        echo "✅ Пакет $package уже установлен"
    else
        echo "📦 Устанавливаем $package..."
        sudo apt install -y $package
    fi
}

# Отключаем обновление command-not-found
echo "🔧 Отключаем обновление command-not-found..."
sudo rm -f /etc/apt/apt.conf.d/99command-not-found
echo 'APT::Update::Post-Invoke-Success "";' | sudo tee /etc/apt/apt.conf.d/99disable-command-not-found > /dev/null

# Обновляем систему
echo "📦 Обновляем систему..."
sudo apt update -o APT::Update::Post-Invoke-Success="" 2>/dev/null || true

# Проверяем и устанавливаем необходимые пакеты
echo "📦 Проверяем необходимые пакеты..."
check_and_install_package "python3"
check_and_install_package "python3-pip"
check_and_install_package "python3-venv"
check_and_install_package "nginx"
check_and_install_package "redis-server"
check_and_install_package "postgresql"
check_and_install_package "postgresql-contrib"
check_and_install_package "git"
check_and_install_package "curl"

# Создаем пользователя для проекта (если не существует)
if ! id "django" &>/dev/null; then
    echo "👤 Создаем пользователя django..."
    sudo useradd -m -s /bin/bash django
    sudo usermod -aG sudo django
    # Настраиваем sudo без пароля для django
    echo "django ALL=(ALL) NOPASSWD:ALL" | sudo tee /etc/sudoers.d/django
fi

# Создаем директорию для проекта
echo "📁 Создаем директорию для проекта..."
sudo mkdir -p /var/www/$DOMAIN
sudo chown -R django:django /var/www/$DOMAIN

# Проверяем наличие файлов проекта
if [ ! -f "/var/www/$DOMAIN/manage.py" ]; then
    echo "❌ Ошибка: Файлы проекта не найдены в /var/www/$DOMAIN/"
    echo "📋 Пожалуйста, загрузите файлы проекта в /var/www/$DOMAIN/ через FTP"
    echo "📁 Необходимые файлы:"
    echo "   - manage.py"
    echo "   - config/ (папка с настройками)"
    echo "   - apps/ (папка с приложениями)"
    echo "   - requirements/ (папка с зависимостями)"
    echo "   - templates/ (папка с шаблонами)"
    echo "   - static/ (папка со статическими файлами)"
    exit 1
fi

echo "✅ Файлы проекта найдены в /var/www/$DOMAIN/"

# Переключаемся на пользователя django
sudo -u django bash << EOF
cd /var/www/$DOMAIN

# Пересоздаем виртуальное окружение для правильной установки пакетов
echo "🐍 Пересоздаем виртуальное окружение..."
rm -rf venv
python3 -m venv venv

# Активируем виртуальное окружение
source venv/bin/activate

# Устанавливаем зависимости
echo "📦 Устанавливаем зависимости..."
pip install --upgrade pip

# Устанавливаем только базовые зависимости (без проблемного django-security)
if [ -f "requirements/base.txt" ]; then
    pip install -r requirements/base.txt
fi

# Устанавливаем gunicorn
pip install gunicorn

# Устанавливаем дополнительные пакеты без проблемных
pip install sentry-sdk[django] || true
pip install django-cachalot || true
pip install structlog || true

# Настраиваем переменные окружения (если файл не существует)
if [ ! -f ".env" ]; then
    echo "⚙️ Создаем файл переменных окружения..."
    cat > .env << EOL
DEBUG=False
SECRET_KEY=\$(python3 -c 'from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())')
ALLOWED_HOSTS=$DOMAIN,www.$DOMAIN
DATABASE_URL=postgresql://django:django_password@localhost/django_db
CELERY_BROKER_URL=redis://localhost:6379/0
CELERY_RESULT_BACKEND=redis://localhost:6379/0
EOL
fi

# Обновляем ALLOWED_HOSTS в .env файле
echo "🔧 Обновляем ALLOWED_HOSTS..."
sed -i "s/ALLOWED_HOSTS=.*/ALLOWED_HOSTS=$DOMAIN,www.$DOMAIN/" .env

# Создаем базу данных PostgreSQL
echo "🗄️ Настраиваем базу данных..."
sudo -u postgres psql << 'POSTGRES_EOF'
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_database WHERE datname = 'django_db') THEN
        CREATE DATABASE django_db;
    END IF;
END
\$\$;

DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'django') THEN
        CREATE USER django WITH PASSWORD 'django_password';
        GRANT ALL PRIVILEGES ON DATABASE django_db TO django;
        ALTER USER django CREATEDB;
    END IF;
END
\$\$;
POSTGRES_EOF

# Выполняем миграции
echo "🔄 Выполняем миграции..."
python manage.py migrate

# Создаем суперпользователя (если не существует)
echo "👑 Создаем суперпользователя..."
python manage.py createsuperuser --noinput --username admin --email admin@$DOMAIN || true

# Собираем статические файлы
echo "📁 Собираем статические файлы..."
python manage.py collectstatic --noinput

# Создаем директории для медиа файлов
mkdir -p media/posters media/videos media/previews media/avatars

# Создаем директорию для логов
mkdir -p logs

# Устанавливаем права доступа
chmod -R 755 media/
chmod -R 755 static/
chmod -R 755 logs/

EOF

# Настраиваем Nginx
echo "🌐 Настраиваем Nginx..."

# Проверяем что Nginx установлен
if ! command -v nginx >/dev/null 2>&1; then
    echo "❌ Nginx не установлен, устанавливаем..."
    sudo apt install -y nginx
fi

# Проверяем структуру Nginx
if [ ! -d "/etc/nginx" ]; then
    echo "❌ Директория /etc/nginx не существует, создаем..."
    sudo mkdir -p /etc/nginx/sites-available
    sudo mkdir -p /etc/nginx/sites-enabled
fi

# Удаляем только конфигурацию по умолчанию (если существует)
if [ -f "/etc/nginx/sites-enabled/default" ]; then
    echo "🗑️ Удаляем конфигурацию по умолчанию..."
    sudo rm -f /etc/nginx/sites-enabled/default
fi

sudo tee /etc/nginx/sites-available/$DOMAIN << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name $DOMAIN www.$DOMAIN;
    
    # Максимальный размер загружаемых файлов
    client_max_body_size 500M;
    
    # Статические файлы
    location /static/ {
        alias /var/www/$DOMAIN/static/;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Медиа файлы
    location /media/ {
        alias /var/www/$DOMAIN/media/;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Основное приложение
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }
}
EOF

# Активируем сайт
sudo ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/

# Проверяем конфигурацию Nginx
echo "🔍 Проверяем конфигурацию Nginx..."
if sudo nginx -t; then
    echo "✅ Конфигурация Nginx корректна"
    # Перезагружаем Nginx
    sudo systemctl reload nginx
else
    echo "❌ Ошибка в конфигурации Nginx"
    echo "🔧 Восстанавливаем базовую конфигурацию..."
    
    # Создаем простую конфигурацию
    sudo tee /etc/nginx/sites-available/$DOMAIN << 'EOF'
server {
    listen 80;
    server_name _;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
EOF
    
    sudo nginx -t && sudo systemctl reload nginx
fi

# Настраиваем systemd сервис для Django
echo "🔧 Настраиваем systemd сервис..."
sudo tee /etc/systemd/system/django-$DOMAIN.service << EOF
[Unit]
Description=Django+HTMX Project - $DOMAIN
After=network.target

[Service]
Type=simple
User=django
Group=django
WorkingDirectory=/var/www/$DOMAIN
Environment=PATH=/var/www/$DOMAIN/venv/bin
ExecStart=/var/www/$DOMAIN/venv/bin/gunicorn config.wsgi:application --bind 127.0.0.1:8000 --workers 3 --timeout 120 --env DJANGO_SETTINGS_MODULE=config.settings.production
ExecReload=/bin/kill -s HUP \$MAINPID
Restart=always

[Install]
WantedBy=multi-user.target
EOF

# Настраиваем systemd сервис для Celery
echo "🔧 Настраиваем Celery сервис..."
sudo tee /etc/systemd/system/celery-$DOMAIN.service << EOF
[Unit]
Description=Celery Worker - $DOMAIN
After=network.target

[Service]
Type=simple
User=django
Group=django
WorkingDirectory=/var/www/$DOMAIN
Environment=PATH=/var/www/$DOMAIN/venv/bin
ExecStart=/var/www/$DOMAIN/venv/bin/celery -A config worker --loglevel=info
ExecReload=/bin/kill -s HUP \$MAINPID
Restart=always

[Install]
WantedBy=multi-user.target
EOF

# Запускаем сервисы
echo "🚀 Запускаем сервисы..."
sudo systemctl daemon-reload
sudo systemctl enable django-$DOMAIN celery-$DOMAIN redis-server postgresql nginx
sudo systemctl start redis-server postgresql nginx
sleep 5
sudo systemctl start django-$DOMAIN
sleep 3
sudo systemctl start celery-$DOMAIN

# Исправляем проблему с certbot и настраиваем SSL
echo "🔒 Исправляем certbot и настраиваем SSL..."

# Переустанавливаем certbot с исправлением зависимостей
echo "🔧 Переустанавливаем certbot..."
sudo apt remove -y certbot python3-certbot-nginx || true
sudo apt autoremove -y || true

# Устанавливаем необходимые пакеты для Python
sudo apt install -y python3-dev python3-pip python3-venv libssl-dev libffi-dev build-essential

# Переустанавливаем certbot через snap (более надежный способ)
if command -v snap >/dev/null 2>&1; then
    echo "📦 Устанавливаем certbot через snap..."
    sudo snap install core; sudo snap refresh core
    sudo snap install --classic certbot
    sudo ln -sf /snap/bin/certbot /usr/bin/certbot
else
    echo "📦 Устанавливаем certbot через pip..."
    sudo python3 -m pip install --upgrade pip
    sudo python3 -m pip install certbot certbot-nginx
fi

# Проверяем работу certbot
if command -v certbot >/dev/null 2>&1; then
    echo "✅ Certbot установлен, получаем SSL сертификат..."
    if [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
        # Получаем сертификат
        sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN --redirect || echo "⚠️ SSL настройка пропущена из-за ошибки"
    else
        echo "✅ SSL сертификат уже настроен для $DOMAIN"
    fi
    
    # Настраиваем автоматическое обновление SSL
    echo "🔄 Настраиваем автоматическое обновление SSL..."
    if command -v snap >/dev/null 2>&1; then
        (crontab -l 2>/dev/null; echo "0 12 * * * /snap/bin/certbot renew --quiet") | crontab -
    else
        (crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
    fi
else
    echo "⚠️ Certbot не удалось установить, SSL настройка пропущена"
    echo "💡 Рекомендация: Настройте SSL вручную позже"
fi

# Исправляем проблемы с ALLOWED_HOSTS
echo "🔧 Исправляем ALLOWED_HOSTS в настройках Django..."
sudo -u django bash << EOF
cd /var/www/$DOMAIN
source venv/bin/activate

# Обновляем настройки Django для продакшена
cat > config/settings/production.py << 'SETTINGS_EOF'
import os
from .base import *

DEBUG = False

ALLOWED_HOSTS = [
    'localhost',
    '127.0.0.1',
    '$DOMAIN',
    'www.$DOMAIN',
]

# Настройки базы данных
DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.postgresql',
        'NAME': 'django_db',
        'USER': 'django',
        'PASSWORD': 'django_password',
        'HOST': 'localhost',
        'PORT': '5432',
    }
}

# Настройки Redis
CACHES = {
    'default': {
        'BACKEND': 'django_redis.cache.RedisCache',
        'LOCATION': 'redis://127.0.0.1:6379/1',
        'OPTIONS': {
            'CLIENT_CLASS': 'django_redis.client.DefaultClient',
        }
    }
}

# Настройки безопасности
SECURE_BROWSER_XSS_FILTER = True
SECURE_CONTENT_TYPE_NOSNIFF = True
X_FRAME_OPTIONS = 'DENY'

# Настройки статических файлов
STATIC_ROOT = os.path.join(BASE_DIR, 'staticfiles')
MEDIA_ROOT = os.path.join(BASE_DIR, 'media')
SETTINGS_EOF

EOF

# Перезапускаем Django сервис
echo "🔄 Перезапускаем Django сервис..."
sudo systemctl restart django-$DOMAIN

# Проверяем статус сервисов
echo "📊 Проверяем статус сервисов..."
sleep 3

echo "Django статус:"
sudo systemctl status django-$DOMAIN --no-pager -l | head -10

echo "Celery статус:"
sudo systemctl status celery-$DOMAIN --no-pager -l | head -10

echo "Nginx статус:"
sudo systemctl status nginx --no-pager -l | head -5

echo "✅ Развертывание завершено!"
echo ""
echo "🌐 Ваш сайт доступен по адресу: https://$DOMAIN"
echo "👑 Админка: https://$DOMAIN/admin/"
echo ""
echo "📝 Полезные команды:"
echo "   Перезапуск Django: sudo systemctl restart django-$DOMAIN"
echo "   Перезапуск Celery: sudo systemctl restart celery-$DOMAIN"
echo "   Логи Django: sudo journalctl -u django-$DOMAIN -f"
echo "   Логи Celery: tail -f /var/www/$DOMAIN/logs/celery.log"
echo ""
echo "📁 Директория проекта: /var/www/$DOMAIN/"
echo "🔧 Для обновления файлов загружайте их в /var/www/$DOMAIN/"
echo ""
echo "🎉 Проект успешно развернут!"
