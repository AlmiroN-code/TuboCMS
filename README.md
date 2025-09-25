# Video Portal - Django + HTMX

Современный видео портал, построенный на Django и HTMX с интерактивными функциями без перезагрузки страниц.

## 🚀 Основные возможности

### 📹 Видео функциональность
- **Загрузка и публикация видео** (MP4, AVI, MOV, WMV, FLV, WebM)
- **Автоматическая обработка видео** с помощью Celery + FFmpeg:
  - Извлечение постера из середины видео
  - Создание видео-превью (12 секунд из кусочков по 2 секунды)
  - Извлечение продолжительности видео
- **Интерактивные превью** - при наведении на постер воспроизводится превью
- **Рейтинговая система** с лайками/дизлайками и визуальными индикаторами

### 🏷️ Организация контента
- **Категории и теги** для организации видео
- **Система актеров** с профилями
- **Поиск и фильтрация** с автодополнением через HTMX
- **Плейлисты** для пользователей
- **Закладки "Посмотреть позже"**

### 💬 Социальные функции
- **Комментарии в реальном времени** через HTMX
- **Лайки/дизлайки** с мгновенным обновлением
- **Подписки на каналы** с обновлением счетчиков
- **История просмотров**

### 🎨 Интерфейс
- **Адаптивный дизайн** для всех устройств
- **Темная/светлая тема** с сохранением настроек
- **Бесконечная прокрутка** для загрузки видео
- **HTMX интеграция** для динамических обновлений без перезагрузки

## 🛠️ Технологический стек

- **Backend**: Django 4.2+ с модульной архитектурой
- **Frontend**: HTMX для интерактивности, чистый CSS
- **База данных**: PostgreSQL (или SQLite для разработки)
- **Очереди задач**: Celery + Redis
- **Обработка видео**: FFmpeg
- **Кэширование**: Redis
- **Статические файлы**: WhiteNoise

## 📋 Требования

### Системные требования
- Python 3.9+
- PostgreSQL 12+ (опционально, можно использовать SQLite)
- Redis 6+
- FFmpeg (для обработки видео)

### Установка системных зависимостей

#### Ubuntu/Debian:
```bash
sudo apt update
sudo apt install python3.9 python3.9-venv python3-pip postgresql redis-server ffmpeg
```

#### macOS:
```bash
brew install python3 postgresql redis ffmpeg
```

#### Windows:
- Установите Python 3.9+ с [python.org](https://python.org)
- Установите PostgreSQL с [postgresql.org](https://postgresql.org)
- Установите Redis с [redis.io](https://redis.io)
- Установите FFmpeg с [ffmpeg.org](https://ffmpeg.org)

## 🚀 Быстрый старт

### 1. Клонирование и настройка

```bash
# Клонируйте проект
git clone <repository-url>
cd Django+HTMX

# Создайте виртуальное окружение
python3 -m venv venv
source venv/bin/activate  # Linux/macOS
# или
venv\Scripts\activate  # Windows

# Установите зависимости
pip install -r requirements/local.txt
```

### 2. Настройка окружения

```bash
# Скопируйте файл с переменными окружения
cp env.example .env

# Отредактируйте .env файл
nano .env  # или любой другой редактор
```

Минимальные настройки для разработки:
```env
SECRET_KEY=your-secret-key-here
DEBUG=True
ALLOWED_HOSTS=localhost,127.0.0.1
```

### 3. Настройка базы данных

#### Для SQLite (простая разработка):
```bash
# Миграции уже настроены для SQLite
python manage.py migrate
```

#### Для PostgreSQL:
```bash
# Создайте базу данных
sudo -u postgres createdb videoportal

# Обновите .env файл:
# DB_NAME=videoportal
# DB_USER=postgres
# DB_PASSWORD=your-password

# Выполните миграции
python manage.py migrate
```

### 4. Создание суперпользователя

```bash
python manage.py createsuperuser
```

### 5. Запуск сервера разработки

```bash
# Запустите Django сервер
python manage.py runserver

# В другом терминале запустите Celery worker (для обработки видео)
celery -A config worker --loglevel=info

# В третьем терминале запустите Celery beat (для периодических задач)
celery -A config beat --loglevel=info
```

### 6. Доступ к приложению

- **Основной сайт**: http://localhost:8000
- **Админ панель**: http://localhost:8000/admin
- **API документация**: http://localhost:8000/api/

## 📁 Структура проекта

```
Django+HTMX/
├── manage.py
├── config/                 # Основной конфигурационный пакет Django
│   ├── __init__.py
│   ├── settings/
│   │   ├── base.py         # Базовые настройки
│   │   ├── local.py        # Настройки для разработки
│   │   └── production.py   # Настройки для продакшена
│   ├── urls.py
│   ├── wsgi.py
│   ├── asgi.py
│   └── celery.py           # Конфигурация Celery
│
├── apps/                   # Все приложения проекта
│   ├── core/               # Общие компоненты
│   │   ├── models.py       # Базовые модели и миксины
│   │   └── managers.py     # Кастомные менеджеры
│   ├── accounts/           # Пользователи и профили
│   │   ├── models.py       # User, Profile, Subscription и др.
│   │   └── admin.py
│   ├── videos/             # Основная логика видеохостинга
│   │   ├── models.py       # Video, Category, Tag, Actor
│   │   ├── views.py        # Представления
│   │   ├── forms.py        # Формы
│   │   ├── tasks.py        # Celery задачи для обработки видео
│   │   └── admin.py
│   └── comments/           # Комментарии
│       ├── models.py       # Comment, CommentLike
│       └── admin.py
│
├── templates/              # Шаблоны Django
│   ├── base.html           # Базовый шаблон с HTMX
│   └── videos/             # Шаблоны для видео
│       ├── home.html
│       ├── video_detail.html
│       ├── video_upload.html
│       └── partials/       # Частичные шаблоны для HTMX
│
├── static/                 # Статические файлы
│   ├── css/
│   ├── js/
│   └── img/
│
├── media/                  # Медиафайлы (загружаемые пользователями)
│   ├── videos/             # Видео файлы
│   ├── posters/            # Постеры видео
│   ├── previews/           # Превью видео
│   ├── avatars/            # Аватары пользователей
│   └── actors/             # Фото актеров
│
├── requirements/           # Зависимости
│   ├── base.txt            # Базовые зависимости
│   ├── local.txt           # Для разработки
│   └── production.txt      # Для продакшена
│
└── env.example             # Пример переменных окружения
```

## 🎯 Основные функции HTMX

### Динамический поиск
```html
<input 
    type="search" 
    hx-get="/search-suggestions/" 
    hx-trigger="keyup changed delay:500ms"
    hx-target="#search-results"
>
```

### Бесконечная прокрутка
```html
<div 
    hx-get="/load-more-videos/" 
    hx-trigger="revealed"
    hx-swap="beforeend"
>
```

### Лайки без перезагрузки
```html
<button 
    hx-post="/like-video/{{ video.id }}/"
    hx-target="#rating-container"
    hx-swap="innerHTML"
>
    👍 {{ video.like_count }}
</button>
```

### Интерактивные комментарии
```html
<form 
    hx-post="/add-comment/"
    hx-target="#comments-list"
    hx-swap="beforeend"
>
    <!-- форма комментария -->
</form>
```

## 🔧 Настройка для продакшена

### 1. Настройка переменных окружения

```env
DEBUG=False
SECRET_KEY=your-production-secret-key
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com

# База данных PostgreSQL
DB_NAME=videoportal_prod
DB_USER=videoportal_user
DB_PASSWORD=secure-password
DB_HOST=localhost
DB_PORT=5432

# Redis
REDIS_URL=redis://localhost:6379/0
CELERY_BROKER_URL=redis://localhost:6379/0
CELERY_RESULT_BACKEND=redis://localhost:6379/0

# Email
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_HOST_USER=your-email@gmail.com
EMAIL_HOST_PASSWORD=your-app-password
DEFAULT_FROM_EMAIL=noreply@yourdomain.com
```

### 2. Установка зависимостей

```bash
pip install -r requirements/production.txt
```

### 3. Сборка статических файлов

```bash
python manage.py collectstatic --noinput
```

### 4. Запуск с Gunicorn

```bash
gunicorn config.wsgi:application --bind 0.0.0.0:8000
```

### 5. Настройка Nginx (пример)

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    
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
    }
}
```

## 🧪 Тестирование

```bash
# Запуск тестов
python manage.py test

# С покрытием кода
pip install coverage
coverage run --source='.' manage.py test
coverage report
coverage html
```

## 📊 Мониторинг и логирование

### Логирование
Логи сохраняются в `logs/django.log` и выводятся в консоль.

### Мониторинг Celery
```bash
# Мониторинг задач Celery
celery -A config flower
```

## 🤝 Разработка

### Стиль кода
Проект использует:
- **Black** для форматирования
- **isort** для сортировки импортов
- **flake8** для проверки стиля
- **mypy** для проверки типов

```bash
# Форматирование кода
black .
isort .

# Проверка стиля
flake8 .
mypy .
```

### Создание миграций
```bash
# После изменения моделей
python manage.py makemigrations
python manage.py migrate
```

### Создание суперпользователя
```bash
python manage.py createsuperuser
```

## 📝 API

Проект включает REST API на Django REST Framework:

- `GET /api/videos/` - Список видео
- `GET /api/videos/{id}/` - Детали видео
- `POST /api/videos/` - Создание видео (требует авторизации)
- `GET /api/categories/` - Список категорий
- `GET /api/tags/` - Список тегов

## 🔒 Безопасность

- CSRF защита включена
- XSS защита
- SQL инъекции предотвращены через ORM
- Загрузка файлов ограничена по типу и размеру
- Аутентификация через Django Auth

## 📈 Производительность

- Кэширование с Redis
- Оптимизированные запросы с `select_related` и `prefetch_related`
- Ленивая загрузка изображений
- Сжатие статических файлов
- CDN готовность

## 🐛 Отладка

### Режим отладки
```bash
# Включите Django Debug Toolbar
pip install django-debug-toolbar
```

### Логи Celery
```bash
# Подробные логи Celery
celery -A config worker --loglevel=debug
```

## 📞 Поддержка

Если у вас возникли вопросы или проблемы:

1. Проверьте логи в `logs/django.log`
2. Убедитесь, что все зависимости установлены
3. Проверьте настройки в `.env` файле
4. Убедитесь, что Redis и Celery запущены

## 📄 Лицензия

Этот проект распространяется под лицензией MIT. См. файл LICENSE для подробностей.

## 🎉 Заключение

Video Portal - это полнофункциональный видео хостинг с современными технологиями Django и HTMX. Проект следует принципам чистой архитектуры, модульности и масштабируемости.

Основные преимущества:
- ✅ Современный интерактивный интерфейс без сложного JavaScript
- ✅ Автоматическая обработка видео с FFmpeg
- ✅ Масштабируемая архитектура с Celery
- ✅ Полная админ панель для управления
- ✅ Адаптивный дизайн для всех устройств
- ✅ Система рейтингов и комментариев
- ✅ Поиск и фильтрация в реальном времени

Наслаждайтесь разработкой! 🚀
