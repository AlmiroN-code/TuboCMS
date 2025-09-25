# 🚀 Развертывание Django+HTMX проекта

## Развертывание с ручной загрузкой файлов

### 1. Подготовка сервера
- Ubuntu 20.04+ или Debian 11+
- Минимум 2GB RAM, 20GB SSD
- Домен, указывающий на IP сервера

### 2. Загрузка файлов проекта

**Перед запуском скрипта развертывания необходимо загрузить файлы проекта по FTP в директорию `/var/www/доменное_имя/`**

Необходимые файлы и папки:
```
/var/www/yourdomain.com/
├── manage.py
├── config/
│   ├── __init__.py
│   ├── settings/
│   ├── urls.py
│   ├── wsgi.py
│   └── asgi.py
├── apps/
│   ├── accounts/
│   ├── videos/
│   ├── comments/
│   └── core/
├── requirements/
│   ├── base.txt
│   ├── local.txt
│   └── production.txt
├── templates/
├── static/
└── media/
```

### 3. Запуск развертывания

```bash
# Скачиваем скрипт развертывания
wget https://raw.githubusercontent.com/yourusername/Django+HTMX_Project/main/deploy.sh
chmod +x deploy.sh

# Запускаем развертывание (замените на ваш домен)
./deploy.sh yourdomain.com
```

### 4. Что делает скрипт

Скрипт автоматически:
- ✅ Проверяет и устанавливает необходимые пакеты (если не установлены)
- ✅ Создает пользователя `django`
- ✅ Проверяет наличие файлов проекта в `/var/www/доменное_имя/`
- ✅ Создает виртуальное окружение
- ✅ Устанавливает зависимости из requirements/
- ✅ Настраивает PostgreSQL базу данных
- ✅ Создает файл .env с настройками
- ✅ Выполняет миграции Django
- ✅ Создает суперпользователя
- ✅ Собирает статические файлы
- ✅ Настраивает Nginx
- ✅ Создает systemd сервисы
- ✅ Настраивает SSL сертификат Let's Encrypt
- ✅ Запускает все сервисы

### 5. После развертывания

Ваш сайт будет доступен по адресу: `https://yourdomain.com`

**Админка:** `https://yourdomain.com/admin/`
- Логин: `admin`
- Пароль: нужно будет установить

## Управление проектом

### Обновление файлов

Для обновления файлов проекта:
1. Загрузите новые файлы по FTP в `/var/www/доменное_имя/`
2. Перезапустите сервисы:

```bash
# Перезапуск Django
sudo systemctl restart django-yourdomain.com

# Перезапуск Celery
sudo systemctl restart celery-yourdomain.com

# Перезапуск Nginx
sudo systemctl reload nginx
```

### Полезные команды

```bash
# Статус сервисов
sudo systemctl status django-yourdomain.com
sudo systemctl status celery-yourdomain.com
sudo systemctl status nginx

# Логи
sudo journalctl -u django-yourdomain.com -f
tail -f /var/www/yourdomain.com/logs/celery.log

# Перезапуск сервисов
sudo systemctl restart django-yourdomain.com
sudo systemctl restart celery-yourdomain.com

# Сбор статических файлов
cd /var/www/yourdomain.com
source venv/bin/activate
python manage.py collectstatic --noinput

# Выполнение миграций
python manage.py migrate
```

## Ручное развертывание

Если автоматический скрипт не подходит, можно развернуть вручную:

### 1. Установка зависимостей

```bash
sudo apt update
sudo apt install -y python3 python3-pip python3-venv nginx redis-server postgresql postgresql-contrib git
```

### 2. Настройка базы данных

```bash
sudo -u postgres psql
CREATE DATABASE django_db;
CREATE USER django WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE django_db TO django;
\q
```

### 3. Клонирование и настройка проекта

```bash
git clone https://github.com/yourusername/Django+HTMX_Project.git
cd Django+HTMX_Project
python3 -m venv venv
source venv/bin/activate
pip install -r requirements/base.txt -r requirements/production.txt
```

### 4. Настройка переменных окружения

Создайте файл `.env`:

```bash
DEBUG=False
SECRET_KEY=your-secret-key-here
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com
DB_NAME=django_db
DB_USER=django
DB_PASSWORD=your_password
DB_HOST=localhost
DB_PORT=5432
REDIS_URL=redis://127.0.0.1:6379/1
CELERY_BROKER_URL=redis://localhost:6379/0
CELERY_RESULT_BACKEND=redis://localhost:6379/0
```

### 5. Миграции и статические файлы

```bash
python manage.py migrate
python manage.py collectstatic --noinput
python manage.py createsuperuser
```

### 6. Настройка Nginx

```bash
sudo nano /etc/nginx/sites-available/yourdomain.com
```

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    
    client_max_body_size 500M;
    
    location /static/ {
        alias /path/to/your/project/staticfiles/;
    }
    
    location /media/ {
        alias /path/to/your/project/media/;
    }
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/yourdomain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. Настройка SSL

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### 8. Запуск сервисов

```bash
# Django
gunicorn config.wsgi:application --bind 127.0.0.1:8000 --workers 3

# Celery (в отдельном терминале)
celery -A config worker --loglevel=info
```

## Управление сервисами

### Systemd сервисы (рекомендуется)

Создайте файлы сервисов для автоматического запуска:

**Django сервис** (`/etc/systemd/system/django.service`):
```ini
[Unit]
Description=Django+HTMX Project
After=network.target

[Service]
Type=simple
User=django
WorkingDirectory=/path/to/your/project
Environment=PATH=/path/to/your/project/venv/bin
ExecStart=/path/to/your/project/venv/bin/gunicorn config.wsgi:application --bind 127.0.0.1:8000 --workers 3
Restart=always

[Install]
WantedBy=multi-user.target
```

**Celery сервис** (`/etc/systemd/system/celery.service`):
```ini
[Unit]
Description=Celery Worker
After=network.target

[Service]
Type=forking
User=django
WorkingDirectory=/path/to/your/project
Environment=PATH=/path/to/your/project/venv/bin
ExecStart=/path/to/your/project/venv/bin/celery -A config worker --detach --loglevel=info
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable django celery
sudo systemctl start django celery
```

## Полезные команды

```bash
# Статус сервисов
sudo systemctl status django
sudo systemctl status celery
sudo systemctl status nginx

# Перезапуск сервисов
sudo systemctl restart django
sudo systemctl restart celery
sudo systemctl restart nginx

# Логи
sudo journalctl -u django -f
tail -f logs/celery.log
tail -f logs/django.log

# Обновление проекта
git pull
source venv/bin/activate
pip install -r requirements/production.txt
python manage.py migrate
python manage.py collectstatic --noinput
sudo systemctl restart django celery
```

## Мониторинг

### Проверка работоспособности

```bash
# Проверка Django
curl -I https://yourdomain.com

# Проверка статических файлов
curl -I https://yourdomain.com/static/css/custom.css

# Проверка медиа файлов
curl -I https://yourdomain.com/media/posters/logo.png
```

### Логи

- **Django логи:** `logs/django.log`
- **Celery логи:** `logs/celery.log`
- **Nginx логи:** `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- **Systemd логи:** `sudo journalctl -u django -f`

## Безопасность

### Рекомендации

1. **Регулярно обновляйте систему:**
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

2. **Настройте файрвол:**
   ```bash
   sudo ufw enable
   sudo ufw allow 22
   sudo ufw allow 80
   sudo ufw allow 443
   ```

3. **Используйте сильные пароли** для базы данных и суперпользователя

4. **Регулярно создавайте резервные копии:**
   ```bash
   # Бэкап базы данных
   pg_dump django_db > backup_$(date +%Y%m%d).sql
   
   # Бэкап медиа файлов
   tar -czf media_backup_$(date +%Y%m%d).tar.gz media/
   ```

## Поддержка

При возникновении проблем:

1. Проверьте логи сервисов
2. Убедитесь, что все сервисы запущены
3. Проверьте настройки Nginx
4. Проверьте права доступа к файлам

**Проект готов к коммерческому использованию!** 🎉
