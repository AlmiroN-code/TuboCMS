# 🚀 БЫСТРОЕ РАЗВЕРТЫВАНИЕ НА ДОМЕНЕ

## ⚡ Развертывание с загрузкой файлов по FTP

### 1. Подготовка
- Сервер Ubuntu 20.04+ (минимум 2GB RAM)
- Домен, указывающий на IP сервера
- SSH доступ к серверу
- FTP доступ для загрузки файлов

### 2. Загрузка файлов проекта

**ПЕРЕД запуском скрипта развертывания:**

1. Загрузите файлы проекта по FTP в `/var/www/доменное_имя/`
2. Убедитесь, что загружены все необходимые файлы:
   - `manage.py`
   - `config/` (папка с настройками)
   - `apps/` (папка с приложениями)
   - `requirements/` (папка с зависимостями)
   - `templates/` (папка с шаблонами)
   - `static/` (папка со статическими файлами)

### 3. Запуск развертывания

```bash
# На сервере выполните:
wget https://raw.githubusercontent.com/yourusername/Django+HTMX_Project/main/deploy.sh
chmod +x deploy.sh
./deploy.sh yourdomain.com
```

**Всё!** Скрипт автоматически:
- ✅ Проверит и установит все пакеты (если не установлены)
- ✅ Настроит PostgreSQL, Redis, Nginx
- ✅ Создаст виртуальное окружение
- ✅ Установит зависимости из requirements/
- ✅ Выполнит миграции
- ✅ Настроит SSL сертификат
- ✅ Запустит все сервисы

### 4. Результат

🌐 **Ваш сайт:** `https://yourdomain.com`
👑 **Админка:** `https://yourdomain.com/admin/`

## 🔄 Обновление проекта

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

## 📊 Управление сервисами

```bash
# Статус
sudo systemctl status django-yourdomain.com
sudo systemctl status celery-yourdomain.com

# Перезапуск
sudo systemctl restart django-yourdomain.com
sudo systemctl restart celery-yourdomain.com

# Логи
sudo journalctl -u django-yourdomain.com -f
tail -f /var/www/yourdomain.com/logs/celery.log
```

## 🆘 Если что-то пошло не так

1. Проверьте логи: `sudo journalctl -u django-yourdomain.com -f`
2. Проверьте статус: `sudo systemctl status django-yourdomain.com`
3. Перезапустите: `sudo systemctl restart django-yourdomain.com`
4. Проверьте файлы: убедитесь, что все файлы загружены в `/var/www/доменное_имя/`

**Проект готов к коммерческому использованию!** 🎉
