# Исправление ошибки 500 на странице /admin/settings/encoding-profiles в продакшене

## Проблема
Ошибка 500 на странице `/admin/settings/encoding-profiles` в продакшене Ubuntu, хотя в dev режиме на Laragon всё работает.

## Причина
Скорее всего, в продакшене не применена миграция для добавления поля `format` в таблицу `video_encoding_profile`.

## Решение

### Шаг 1: Диагностика проблемы
```bash
# Подключитесь к серверу Ubuntu и перейдите в директорию проекта
cd /var/www/sexvids.online

# Запустите диагностику
php bin/console app:diagnose-encoding-profiles
```

### Шаг 2: Быстрое исправление
```bash
# Автоматическое исправление всех проблем
php bin/console app:fix-encoding-profiles-production

# Очистите кеш продакшена
php bin/console cache:clear --env=prod

# Установите правильные права доступа
sudo chown -R www-data:www-data var/cache
sudo chmod -R 755 var/cache
```

### Шаг 3: Применение миграций (если нужно)
```bash
# Проверьте статус миграций
php bin/console doctrine:migrations:status

# Примените все новые миграции
Давай создадим систему постов.

# Если миграция Version20260123000001 не применена, примените её принудительно
php bin/console doctrine:migrations:execute --up Version20260123000001
```

### Шаг 4: Ручное исправление (если автоматическое не сработало)
```sql
-- Подключитесь к MySQL
mysql -u root -p

-- Выберите базу данных
USE sexvids_online;

-- Проверьте структуру таблицы
DESCRIBE video_encoding_profile;

-- Если колонка format отсутствует, добавьте её
ALTER TABLE video_encoding_profile ADD COLUMN format VARCHAR(10) NOT NULL DEFAULT 'mp4';

-- Обновите существующие записи
UPDATE video_encoding_profile SET format = 'mp4' WHERE format IS NULL OR format = '';

-- Обновите кодеки на правильные значения FFmpeg
UPDATE video_encoding_profile SET codec = 'libx264' WHERE codec IN ('h264', 'x264', 'avc');
UPDATE video_encoding_profile SET codec = 'libx265' WHERE codec IN ('h265', 'x265', 'hevc');
```

### Шаг 5: Проверка
```bash
# Проверьте, что профили загружаются корректно
php bin/console app:list-encoding-profiles

# Проверьте логи на наличие ошибок
tail -f var/log/prod.log
```

## Ожидаемый результат
После выполнения этих шагов:
1. Страница `/admin/settings/encoding-profiles` должна загружаться без ошибок
2. Форма добавления/редактирования профилей должна работать корректно
3. Поле "Формат" должно отображаться и сохраняться правильно

## Дополнительная проверка
Откройте в браузере:
- `https://sexvids.online/admin/settings/encoding-profiles` - должна загружаться без ошибок
- Попробуйте добавить новый профиль кодирования
- Попробуйте отредактировать существующий профиль

## Если проблема остаётся
1. Проверьте логи веб-сервера: `sudo tail -f /var/log/nginx/error.log`
2. Проверьте логи PHP: `sudo tail -f /var/log/php8.4-fpm.log`
3. Включите режим отладки временно в `.env`: `APP_DEBUG=true`
4. Проверьте права доступа к файлам: `ls -la var/`