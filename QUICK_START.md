# RexTube - Быстрый старт

## ✅ Проект успешно запущен!

### 🌐 Доступ к сайту
- **URL**: http://rextube.test:8080/
- **Админ-панель**: http://rextube.test:8080/admin

### 👤 Тестовые пользователи
- **Администратор**: 
  - Email: `admin@rextube.test`
  - Пароль: `admin123`
  
- **Обычный пользователь**: 
  - Email: `user@rextube.test`
  - Пароль: `user123`

## 📋 Что уже настроено

✅ База данных создана и заполнена тестовыми данными  
✅ Фронтенд скомпилирован (Webpack Encore + Tailwind CSS)  
✅ Все зависимости установлены (Composer + npm)  
✅ Сервис EmbedService создан для встраивания видео  
✅ Twig функции для SEO настроек добавлены  

## 🚀 Полезные команды

### Запуск проекта
```bash
# Быстрый запуск (очистка кэша + компиляция фронтенда)
start-project.bat

# Проверка статуса
check-status.bat
```

### Работа с базой данных
```bash
# Очистка и перезагрузка тестовых данных
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console doctrine:fixtures:load --no-interaction

# Обновление схемы БД
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console doctrine:schema:update --force
```

### Работа с фронтендом
```bash
# Разовая компиляция для разработки
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js dev

# Компиляция с отслеживанием изменений
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js dev --watch

# Production сборка
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js production
```

### Очистка кэша
```bash
# Очистка кэша Symfony
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console cache:clear

# Полная очистка (кэш + компиляция)
clear-cache.bat
```

## 📁 Структура проекта

- `src/` - PHP код (контроллеры, сущности, сервисы)
- `templates/` - Twig шаблоны
- `assets/` - Исходники фронтенда (CSS, JS)
- `public/` - Веб-корень (скомпилированные ассеты, загрузки)
- `config/` - Конфигурация Symfony
- `migrations/` - Миграции базы данных

## 🔧 Требования

- ✅ PHP 8.4.15
- ✅ MySQL 8.0
- ✅ Node.js 22.12.0
- ✅ Composer
- ✅ Laragon (Nginx 1.2.7)

## 📝 Основные функции

- Загрузка и просмотр видео
- Категории и теги
- Комментарии с вложенностью (HTMX)
- Поиск по видео
- Личный кабинет пользователя
- Админ-панель для управления контентом
- Асинхронная обработка видео (Symfony Messenger)
- SEO оптимизация (мета-теги, sitemap)
- Встраивание видео (oEmbed)

## 🐛 Решение проблем

### Ошибка "Unknown function"
```bash
# Очистите кэш Symfony
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console cache:clear
```

### Проблемы с базой данных
```bash
# Пересоздайте базу данных
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console doctrine:database:drop --force
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console doctrine:database:create
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console doctrine:schema:update --force
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console doctrine:fixtures:load --no-interaction
```

### Проблемы с фронтендом
```bash
# Пересоберите ассеты
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js dev
```

## 📚 Документация

- [Symfony 8.0](https://symfony.com/doc/8.0/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/)
- [Tailwind CSS](https://tailwindcss.com/docs)
- [Webpack Encore](https://symfony.com/doc/current/frontend.html)

---

**Проект готов к работе! Откройте http://rextube.test:8080/ в браузере.**
