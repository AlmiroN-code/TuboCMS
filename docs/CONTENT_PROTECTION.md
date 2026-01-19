# Система защиты контента RexTube

## Обзор

Система защиты контента предназначена для предотвращения несанкционированного скачивания и использования медиа-файлов (видео, постеры, превью).

## Компоненты системы

### 1. Hotlink Protection (Защита от прямых ссылок)

**Как работает:**
- Проверяет HTTP Referer при каждом запросе к медиа-файлу
- Блокирует запросы с неразрешенных доменов
- Разрешает доступ только с вашего сайта и указанных доменов

**Настройка:**
1. Перейдите в `/admin/settings/protection`
2. Включите "Защита от Hotlink"
3. Добавьте разрешенные домены (по одному на строку)

**Пример:**
```
example.com
partner-site.com
cdn.mysite.com
```

### 2. User-Agent Filtering (Фильтрация User-Agent)

**Как работает:**
- Проверяет User-Agent браузера/приложения
- Блокирует известные скачиватели: wget, curl, youtube-dl, yt-dlp, IDM и др.
- Блокирует ботов и скраперов

**Встроенный список заблокированных:**
- wget, curl
- youtube-dl, yt-dlp
- ffmpeg, aria2
- IDM, FlashGet, GetRight, Download Master
- JDownloader
- python-requests, scrapy
- bot, crawler, spider

**Настройка:**
1. Включите "Фильтрация User-Agent"
2. Добавьте дополнительные User-Agent для блокировки

### 3. Signed URLs (Подписанные URL)

**Как работает:**
- Генерирует временные токены для каждого URL
- Токен привязан к IP адресу и сессии пользователя
- URL действителен ограниченное время (по умолчанию 1 час)
- Использует HMAC-SHA256 для подписи

**Пример URL:**
```
/secure-media/videos/video.mp4?token=abc123...&expires=1234567890
```

**Настройка:**
1. Включите "Подписанные URL"
2. Установите время жизни токена (60-86400 секунд)

**Использование в шаблонах:**
```twig
{# Вместо обычного пути #}
<video src="/media/videos/{{ video.filename }}">

{# Используйте защищенный URL #}
<video src="{{ secure_video_url(video.filename) }}">

{# Для постеров #}
<img src="{{ secure_poster_url(video.poster) }}">

{# Для превью #}
<img src="{{ secure_preview_url(video.preview) }}">
```

### 4. Rate Limiting (Ограничение скорости)

**Как работает:**
- Ограничивает количество запросов с одного IP адреса
- По умолчанию: 100 запросов в час
- Предотвращает массовое скачивание

**Настройка:**
1. Установите максимальное количество запросов в час (10-10000)
2. Рекомендуется: 100-500 для обычных пользователей

**HTTP заголовки ответа:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1234567890
Retry-After: 3600
```

### 5. Watermark (Водяной знак)

**Как работает:**
- Добавляет видимый текст на видео
- Можно использовать переменные: {username}, {site_name}
- Настраиваемая позиция и прозрачность

**Настройка:**
1. Включите "Водяной знак"
2. Укажите текст (например: "© RexTube.com")
3. Выберите позицию (угол или центр)
4. Настройте прозрачность (0-100%)

**Примечание:** Требует перекодирование видео с помощью FFmpeg.

## Архитектура

### Файлы и классы

```
src/
├── Entity/
│   └── ContentProtectionSetting.php      # Настройки защиты
├── Repository/
│   └── ContentProtectionSettingRepository.php
├── Service/
│   └── ContentProtectionService.php      # Основная логика защиты
├── Controller/
│   ├── SecureMediaController.php         # Раздача защищенных файлов
│   └── Admin/
│       └── AdminContentProtectionController.php  # Админка
├── EventSubscriber/
│   └── MediaRateLimitSubscriber.php      # Rate limiting
└── Twig/
    └── SecureMediaExtension.php          # Twig функции

templates/
└── admin/
    └── content_protection/
        └── index.html.twig                # Интерфейс настроек

config/
└── packages/
    └── rate_limiter.yaml                  # Конфигурация лимитов
```

### Маршруты

```
/secure-media/videos/{filename}    - Защищенная раздача видео
/secure-media/posters/{filename}   - Защищенная раздача постеров
/secure-media/previews/{filename}  - Защищенная раздача превью
/admin/settings/protection         - Настройки защиты
```

## Миграция существующих файлов

Если у вас уже есть видео и вы хотите включить защиту:

### Вариант 1: Использовать Signed URLs (рекомендуется)

1. Включите "Подписанные URL" в настройках
2. Обновите шаблоны, используя `secure_video_url()` вместо прямых путей
3. Файлы остаются в `/public/media/`, но доступ через `/secure-media/`

### Вариант 2: Переместить файлы за пределы public

```bash
# Создать защищенную директорию
mkdir -p storage/media/{videos,posters,previews}

# Переместить файлы
mv public/media/videos/* storage/media/videos/
mv public/media/posters/* storage/media/posters/
mv public/media/previews/* storage/media/previews/

# Обновить пути в базе данных
php bin/console app:update-media-paths
```

## Рекомендации по безопасности

### Базовая защита (для начала)
```
✓ Hotlink Protection: Включено
✓ User-Agent Filtering: Включено
✓ Rate Limiting: 100 запросов/час
✗ Signed URLs: Отключено (для простоты)
✗ Watermark: Отключено
```

### Средняя защита (рекомендуется)
```
✓ Hotlink Protection: Включено
✓ User-Agent Filtering: Включено
✓ Signed URLs: Включено (3600 сек)
✓ Rate Limiting: 50 запросов/час
✗ Watermark: Отключено
```

### Максимальная защита
```
✓ Hotlink Protection: Включено
✓ User-Agent Filtering: Включено
✓ Signed URLs: Включено (1800 сек)
✓ Rate Limiting: 30 запросов/час
✓ Watermark: Включено
```

## Мониторинг

### Логи заблокированных запросов

Создайте кастомный логгер для отслеживания:

```php
// src/EventSubscriber/MediaAccessLogger.php
class MediaAccessLogger implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        if ($exception instanceof AccessDeniedHttpException) {
            $request = $event->getRequest();
            
            $this->logger->warning('Media access denied', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referer' => $request->headers->get('referer'),
                'path' => $request->getPathInfo(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
```

### Статистика в админке

Добавьте дашборд с метриками:
- Количество заблокированных запросов
- Топ заблокированных IP
- Топ заблокированных User-Agent
- График попыток доступа

## Производительность

### Кеширование настроек

Настройки защиты кешируются автоматически. Для сброса кеша:

```bash
php bin/console cache:clear
```

### Оптимизация для высоких нагрузок

1. **Используйте CDN** для статических файлов
2. **Настройте nginx** для проверки токенов на уровне веб-сервера
3. **Используйте Redis** для Rate Limiting вместо файлового хранилища

### Пример конфигурации nginx

```nginx
location /secure-media/ {
    # Проверка токена на уровне nginx
    secure_link $arg_token;
    secure_link_md5 "$secure_link_expires$uri$remote_addr secret_key";
    
    if ($secure_link = "") {
        return 403;
    }
    
    if ($secure_link = "0") {
        return 410;
    }
    
    # Проверка Referer
    valid_referers server_names *.example.com;
    if ($invalid_referer) {
        return 403;
    }
    
    # Блокировка User-Agent
    if ($http_user_agent ~* (wget|curl|youtube-dl|yt-dlp)) {
        return 403;
    }
    
    # Раздача файла
    alias /path/to/media/;
}
```

## Тестирование

### Тест Hotlink Protection

```bash
# Должен быть заблокирован (нет referer)
curl -I https://rextube.test/secure-media/videos/test.mp4

# Должен быть заблокирован (чужой referer)
curl -I -H "Referer: https://evil-site.com" https://rextube.test/secure-media/videos/test.mp4

# Должен работать (правильный referer)
curl -I -H "Referer: https://rextube.test" https://rextube.test/secure-media/videos/test.mp4
```

### Тест User-Agent Filtering

```bash
# Должен быть заблокирован
curl -I -A "wget/1.20" https://rextube.test/secure-media/videos/test.mp4

# Должен работать
curl -I -A "Mozilla/5.0" https://rextube.test/secure-media/videos/test.mp4
```

### Тест Signed URLs

```bash
# Без токена - должен быть заблокирован
curl -I https://rextube.test/secure-media/videos/test.mp4

# С валидным токеном - должен работать
curl -I "https://rextube.test/secure-media/videos/test.mp4?token=abc123&expires=9999999999"

# С истекшим токеном - должен быть заблокирован
curl -I "https://rextube.test/secure-media/videos/test.mp4?token=abc123&expires=1"
```

## FAQ

**Q: Повлияет ли это на производительность?**
A: Минимально. Проверки выполняются быстро. Для высоких нагрузок используйте nginx.

**Q: Можно ли обойти защиту?**
A: Любую защиту можно обойти, но это значительно усложняет массовое скачивание.

**Q: Что делать, если легитимные пользователи блокируются?**
A: Ослабьте настройки, добавьте домены в whitelist, увеличьте rate limit.

**Q: Нужно ли перекодировать все видео для watermark?**
A: Да, watermark требует перекодирование. Это ресурсоемкая операция.

**Q: Как защититься от скриншотов и screen recording?**
A: Технически невозможно. Используйте watermark с username для отслеживания утечек.

## Дополнительные меры

### 1. DRM (Digital Rights Management)

Для максимальной защиты рассмотрите:
- **Widevine** (Google)
- **FairPlay** (Apple)
- **PlayReady** (Microsoft)

### 2. HLS Encryption

Шифрование HLS сегментов с AES-128:

```php
// Генерация ключа шифрования
$key = random_bytes(16);
$keyUri = $this->generateKeyUrl($video->getId());

// FFmpeg команда с шифрованием
$command = sprintf(
    'ffmpeg -i %s -hls_key_info_file %s -hls_time 10 -hls_list_size 0 output.m3u8',
    $input,
    $keyInfoFile
);
```

### 3. Geo-blocking

Ограничение доступа по странам:

```php
public function validateGeoLocation(Request $request): bool
{
    $ip = $request->getClientIp();
    $country = $this->geoIpService->getCountry($ip);
    
    $blockedCountries = ['CN', 'RU', 'KP'];
    
    return !in_array($country, $blockedCountries);
}
```

## Поддержка

Для вопросов и предложений:
- GitHub Issues: https://github.com/your-repo/issues
- Email: support@rextube.com
- Документация: https://docs.rextube.com
