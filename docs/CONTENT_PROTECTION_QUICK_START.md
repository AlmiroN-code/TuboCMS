# Быстрый старт: Защита контента

## Что реализовано

Система защиты контента для предотвращения несанкционированного скачивания видео, постеров и превью.

## Компоненты

### 1. База данных
✅ Таблица `content_protection_setting` создана
✅ Миграция выполнена

### 2. Backend
✅ `ContentProtectionService` - основная логика защиты
✅ `SecureMediaController` - раздача защищенных файлов
✅ `AdminContentProtectionController` - админ-панель
✅ `MediaRateLimitSubscriber` - ограничение скорости
✅ `SecureMediaExtension` - Twig функции
✅ `VideoProcessingService` - добавление водяных знаков

### 3. Frontend
✅ Админ-панель: `/admin/settings/protection`
✅ Переводы на русский язык
✅ Шаблоны обновлены для использования `secure_*_url()`

### 4. Тесты
✅ `ContentProtectionServiceTest` - 12 тестов
✅ `SecureMediaControllerTest` - 3 теста

## Быстрая настройка

### Шаг 1: Откройте админ-панель

```
http://rextube.test:8080/admin/settings/protection
```

### Шаг 2: Включите базовую защиту

**Рекомендуемые настройки для начала:**

- ✅ **Hotlink Protection**: Включено
- ✅ **User-Agent Filtering**: Включено  
- ✅ **Rate Limiting**: 100 запросов/час
- ❌ **Signed URLs**: Отключено (пока)
- ❌ **Watermark**: Отключено (пока)

### Шаг 3: Сохраните настройки

Нажмите кнопку "Сохранить"

## Использование в шаблонах

### Автоматическое использование (уже настроено):

Шаблоны `_card.html.twig` и `_player.html.twig` уже используют защищённые URL:

```twig
{# Постер #}
{{ secure_poster_url(video.poster) }}

{# Превью #}
{{ secure_preview_url(video.preview) }}

{# Видео #}
{{ secure_video_url(video.filename) }}
```

### Как это работает:

- Если **Signed URLs отключены** → возвращается обычный путь `/media/...`
- Если **Signed URLs включены** → возвращается защищённый путь `/secure-media/...?token=...&expires=...`

## Маршруты

```
/secure-media/videos/{filename}    - Защищенные видео
/secure-media/posters/{filename}   - Защищенные постеры
/secure-media/previews/{filename}  - Защищенные превью
/admin/settings/protection         - Настройки защиты
```

## Механизмы защиты

### 1. Hotlink Protection
- Проверяет HTTP Referer
- Блокирует запросы с других сайтов
- Разрешает доступ только с вашего домена

### 2. User-Agent Filtering
- Блокирует wget, curl, youtube-dl, yt-dlp
- Блокирует IDM, JDownloader и другие скачиватели
- Блокирует ботов и скраперов

### 3. Signed URLs
- Временные токены в URL
- Привязка к IP и сессии
- Автоматическое истечение

### 4. Rate Limiting
- Ограничение запросов с одного IP
- По умолчанию: 100 запросов/час
- Защита от массового скачивания

### 5. Watermark
- Текстовый водяной знак на видео
- Настраиваемая позиция (углы или центр)
- Настраиваемая прозрачность (0-100%)
- Применяется при кодировании видео

## Тестирование

### Запуск тестов:

```bash
php vendor/bin/phpunit tests/Service/ContentProtectionServiceTest.php
php vendor/bin/phpunit tests/Controller/SecureMediaControllerTest.php
```

### Проверка Hotlink Protection (curl):

```bash
# Без referer - должен блокировать
curl -I http://rextube.test:8080/secure-media/videos/test.mp4

# С правильным referer - должен работать
curl -I -H "Referer: http://rextube.test:8080" http://rextube.test:8080/secure-media/videos/test.mp4
```

### Проверка User-Agent Filtering:

```bash
# wget - должен блокировать
curl -I -A "wget/1.20" http://rextube.test:8080/secure-media/videos/test.mp4

# Браузер - должен работать
curl -I -A "Mozilla/5.0" http://rextube.test:8080/secure-media/videos/test.mp4
```

## Статус реализации

### Этап 1: Базовая защита ✅
- ✅ Hotlink Protection
- ✅ User-Agent Filtering
- ✅ Rate Limiting

### Этап 2: Усиленная защита ✅
- ✅ Signed URLs реализованы
- ✅ Шаблоны обновлены для использования `secure_*_url()`
- ✅ Настраиваемое время жизни токенов

### Этап 3: Максимальная защита ✅
- ✅ Watermark реализован (текстовый и изображение)
- ⬜ Переместить файлы за пределы public/ (опционально)
- ⬜ Настроить HLS шифрование (опционально)

## Файлы системы защиты

```
src/
├── Controller/
│   ├── Admin/AdminContentProtectionController.php
│   └── SecureMediaController.php
├── Entity/ContentProtectionSetting.php
├── Repository/ContentProtectionSettingRepository.php
├── Service/ContentProtectionService.php
├── EventSubscriber/MediaRateLimitSubscriber.php
└── Twig/SecureMediaExtension.php

templates/
├── admin/content_protection/index.html.twig
└── video/
    ├── _card.html.twig (обновлён)
    └── _player.html.twig (обновлён)

tests/
├── Service/ContentProtectionServiceTest.php
└── Controller/SecureMediaControllerTest.php
```

## Поддержка

- Админ-панель: `/admin/settings/protection`
- Логи: `var/log/dev.log`
