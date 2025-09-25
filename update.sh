#!/bin/bash

# Скрипт обновления Django+HTMX проекта
# Использование: ./update.sh

set -e

echo "🔄 Начинаем обновление проекта..."

# Переключаемся на пользователя django
sudo -u django bash << 'EOF'
cd /home/django/Django+HTMX_Project

# Активируем виртуальное окружение
source venv/bin/activate

# Получаем обновления из репозитория
echo "📥 Получаем обновления..."
git pull origin main

# Обновляем зависимости
echo "📦 Обновляем зависимости..."
pip install --upgrade pip
pip install -r requirements/base.txt -r requirements/production.txt

# Выполняем миграции
echo "🔄 Выполняем миграции..."
python manage.py migrate

# Собираем статические файлы
echo "📁 Собираем статические файлы..."
python manage.py collectstatic --noinput

# Очищаем кеш
echo "🧹 Очищаем кеш..."
python manage.py clear_cache || true

EOF

# Перезапускаем сервисы
echo "🚀 Перезапускаем сервисы..."
sudo systemctl restart django
sudo systemctl restart celery

# Проверяем статус сервисов
echo "✅ Проверяем статус сервисов..."
sudo systemctl status django --no-pager -l
sudo systemctl status celery --no-pager -l

echo "🎉 Обновление завершено!"
echo "🌐 Сайт доступен по адресу: https://$(hostname)"
