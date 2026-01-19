# RexTube - Видео хостинг на Symfony 8.0

Видео хостинг на Symfony 8.0 с Tailwind CSS, аналог txxx.com.

## ✅ Реализовано

- ✅ Регистрация и аутентификация пользователей
- ✅ Загрузка видео (до 2GB)
- ✅ Просмотр видео с плеером
- ✅ Категории и теги
- ✅ Поиск видео
- ✅ Комментарии с HTMX (вложенные ответы)
- ✅ Личный кабинет (мои видео)
- ✅ Тестовые данные (20 видео, 2 пользователя)

## Требования

- PHP 8.4+
- MySQL 8.0+
- Composer
- Laragon (или другой веб-сервер)

## Быстрый старт

Проект уже настроен и готов к работе!

### Запуск через Laragon
1. Откройте Laragon
2. Убедитесь что MySQL и Nginx запущены
3. Откройте http://rextube.test:8080

### Тестовые пользователи
- **Админ**: admin@rextube.test / admin123
- **Пользователь**: user@rextube.test / user123

## Переустановка (если нужно)

```bash
# 1. Установка зависимостей
composer install

# 2. Создание БД и миграции
php bin/console doctrine:database:create
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# 3. Загрузка тестовых данных
php bin/console doctrine:fixtures:load --no-interaction
```

## Основные страницы

- `/` - Главная
- `/videos` - Все видео
- `/videos/upload` - Загрузка видео
- `/videos/my-videos` - Мои видео
- `/login` - Вход
- `/register` - Регистрация
- `/category` - Категории

## Технологии

- Symfony 8.0, PHP 8.4
- MySQL 8.0, Doctrine ORM 3.5
- Twig, Tailwind CSS, HTMX
- Laragon (Nginx 1.2.7)

## Что нужно добавить

- [ ] Обработка видео (FFmpeg)
- [x] Админ-панель (Кастомная на Tailwind CSS)
- [ ] Лайки/дизлайки
- [ ] Избранное и плейлисты
- [ ] Подписки на каналы
- [ ] Уведомления
