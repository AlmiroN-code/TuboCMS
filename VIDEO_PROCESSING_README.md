# Video Processing System

## Обзор

Система обработки видео с использованием Django + Celery + FFmpeg для автоматического создания постеров и превью видео.

## Возможности

### Обработка видео
- **Извлечение постера**: Создание постера размером 250x150 из середины видео
- **Создание превью**: Генерация 12-секундного превью из 2-секундных сегментов
- **Извлечение метаданных**: Автоматическое определение длительности и размера файла
- **Настраиваемые параметры**: Гибкие настройки через админ-панель

### Интерактивные функции
- **Превью при наведении**: Воспроизведение превью при наведении курсора на постер
- **Прогресс обработки**: Отображение этапов обработки видео
- **Автоматическое обновление**: HTMX для динамического обновления интерфейса

## Установка и настройка

### Требования
- Python 3.12+
- FFmpeg и FFprobe
- Redis (для Celery)
- PostgreSQL (рекомендуется) или SQLite

### Установка зависимостей
```bash
# Активация виртуального окружения
source venv/bin/activate

# Установка зависимостей
pip install -r requirements/local.txt
```

### Настройка FFmpeg
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install ffmpeg

# Проверка установки
ffmpeg -version
ffprobe -version
```

### Настройка Redis
```bash
# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

## Запуск системы

### 1. Запуск Django сервера
```bash
python manage.py runserver
```

### 2. Запуск Celery worker
```bash
# В отдельном терминале
./start_celery.sh
```

### 3. Создание суперпользователя (если нужно)
```bash
python manage.py createsuperuser
```

## Использование

### Загрузка видео
1. Перейдите на страницу `/videos/upload/`
2. Заполните форму загрузки
3. Выберите видео файл (MP4, AVI, MOV, WMV, FLV, WebM)
4. Нажмите "Загрузить видео"

### Настройка обработки
1. Войдите в админ-панель `/admin/`
2. Перейдите в "Настройки обработки видео"
3. Настройте параметры:
   - Размеры постера и превью
   - Качество сжатия
   - Пресеты FFmpeg
   - Длительность превью

### Просмотр результатов
- Постеры сохраняются в `media/posters/`
- Превью сохраняются в `media/previews/`
- Метаданные обновляются в базе данных

## Структура файлов

```
apps/videos/
├── models.py              # Модели данных
├── tasks.py               # Celery задачи
├── admin.py               # Админ-панель
├── forms.py               # Формы загрузки
└── views.py               # Представления

templates/
├── videos/
│   ├── video_upload.html  # Страница загрузки
│   └── partials/
│       └── video_card.html # Карточка видео
└── includes/
    └── sidebar.html       # Боковая панель

static/
├── css/custom.css         # Стили
└── js/htmx-handlers.js    # JavaScript функции
```

## Технические детали

### Celery задачи
- `process_video()`: Основная задача обработки
- `extract_video_info()`: Извлечение метаданных
- `generate_poster()`: Создание постера
- `generate_preview_video()`: Создание превью

### FFmpeg команды
```bash
# Извлечение постера
ffmpeg -i input.mp4 -ss 30 -vframes 1 -vf "scale=250:150" poster.jpg

# Создание превью
ffmpeg -i input.mp4 -filter_complex "..." -c:v libx264 preview.mp4
```

### Настройки модели
- `VideoProcessingSettings`: Конфигурация обработки
- `Video`: Основная модель видео
- Поддержка статусов обработки

## Отладка

### Логи Celery
```bash
# Просмотр логов worker
tail -f celery.log
```

### Проверка задач
```bash
# В Django shell
python manage.py shell
>>> from apps.videos.tasks import process_video
>>> result = process_video.delay(video_id)
```

### Проверка Redis
```bash
redis-cli ping
```

## Производительность

### Рекомендации
- Используйте SSD для медиафайлов
- Настройте Redis с достаточной памятью
- Мониторьте использование CPU при обработке
- Рассмотрите использование очередей с приоритетами

### Масштабирование
- Запуск нескольких Celery workers
- Использование Redis Cluster
- Разделение обработки на разные серверы

## Безопасность

- Валидация типов файлов
- Ограничение размера файлов
- Санитизация имен файлов
- Изоляция процессов обработки

## Мониторинг

- Статусы обработки в админ-панели
- Логи Celery
- Метрики Redis
- Мониторинг дискового пространства
