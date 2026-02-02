# Основные полезные команды системы

**Перекомпиляция стилей:**
$env:PATH += ";D:\laragon\bin\nodejs\node-v22"; ./node_modules/.bin/encore.cmd production --progress

**Вот полный набор команд для очистки абсолютно всего кэша в Symfony на Ubuntu 24.04:**

1. Symfony Cache (основной кэш)
# Очистка всех кэшей для всех окружений
php bin/console cache:clear --env=prod
php bin/console cache:clear --env=dev
php bin/console cache:clear --env=test

# Прогрев кэша после очистки
php bin/console cache:warmup --env=prod
php bin/console cache:warmup --env=dev

2. Doctrine Cache (база данных)
# Очистка кэша метаданных Doctrine
php bin/console doctrine:cache:clear-metadata --env=prod
php bin/console doctrine:cache:clear-metadata --env=dev

# Очистка кэша запросов
php bin/console doctrine:cache:clear-query --env=prod
php bin/console doctrine:cache:clear-query --env=dev

# Очистка кэша результатов
php bin/console doctrine:cache:clear-result --env=prod
php bin/console doctrine:cache:clear-result --env=dev

3. Twig Templates Cache
# Очистка кэша шаблонов (входит в cache:clear, но можно отдельно)
rm -rf var/cache/*/twig/

4. Routing Cache
# Очистка кэша маршрутов
rm -rf var/cache/*/url_*
rm -rf var/cache/*/App*UrlGenerator*
rm -rf var/cache/*/App*UrlMatcher*

5. Messenger Cache (очереди)
# Очистка таблиц Messenger (если используется Doctrine transport)
php bin/console messenger:setup-transports

6. Webpack Encore / Assets Cache
# Очистка скомпилированных ассетов
rm -rf public/build/*

# Пересборка ассетов
npm run build
# или для продакшена
npm run build

7. OPcache (PHP)
# Перезапуск PHP-FPM для очистки OPcache
sudo systemctl restart php8.4-fpm

# Или если используется Apache
sudo systemctl restart apache2

8. Nginx Cache
# Перезапуск Nginx
sudo systemctl restart nginx

# Очистка кэша Nginx (если настроен)
sudo rm -rf /var/cache/nginx/*

9. Полная очистка файловой системы
# Удаление всех кэш-файлов
rm -rf var/cache/*
rm -rf var/log/*

# Очистка временных файлов
rm -rf /tmp/symfony-cache/*

10. Redis/Memcached (если используется)
# Для Redis
redis-cli FLUSHALL

# Для Memcached
echo 'flush_all' | nc localhost 11211

11. Полный скрипт очистки
Создай файл clear-all-cache.sh:

#!/bin/bash

echo "🧹 Полная очистка кэша Symfony..."

# Symfony cache
echo "📁 Очистка Symfony cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:clear --env=dev --no-warmup

# Doctrine cache
echo "🗄️ Очистка Doctrine cache..."
php bin/console doctrine:cache:clear-metadata --env=prod
php bin/console doctrine:cache:clear-query --env=prod
php bin/console doctrine:cache:clear-result --env=prod

# Файловый кэш
echo "📂 Удаление файлов кэша..."
rm -rf var/cache/*
rm -rf var/log/*

# Assets
echo "🎨 Пересборка ассетов..."
rm -rf public/build/*
npm run build

# PHP OPcache
echo "🔄 Перезапуск PHP-FPM..."
sudo systemctl restart php8.4-fpm

# Nginx
echo "🌐 Перезапуск Nginx..."
sudo systemctl restart nginx

# Прогрев кэша
echo "🔥 Прогрев кэша..."
php bin/console cache:warmup --env=prod

echo "✅ Готово! Весь кэш очищен."
Сделай файл исполняемым и запусти:

chmod +x clear-all-cache.sh
./clear-all-cache.sh
12. Проверка результата
# Проверь размер папки кэша
du -sh var/cache/

# Проверь логи на ошибки
tail -f var/log/prod.log

# Проверь статус сервисов
sudo systemctl status php8.4-fpm nginx
Этот набор команд полностью очистит весь кэш: Symfony, Doctrine, Twig, маршруты, ассеты, OPcache, и веб-сервер. После выполнения сайт будет работать с полностью свежим кэшем.