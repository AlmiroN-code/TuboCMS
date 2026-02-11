# Tech Stack & Environment

## Backend
- **Symfony 8.0** (PHP 8.4+), Doctrine ORM 3.6, MySQL 8.0
- **Security**: Form auth, RBAC (7 roles, 65 permissions), rate limiting, CSRF protection
- **Async**: Messenger with Doctrine transport
- **Caching**: Redis, Doctrine cache pools, materialized views
- **Storage**: 6 адаптеров (Local, S3, DigitalOcean Spaces, FTP, SFTP, BunnyCDN)
- **Video**: FFmpeg для транскодирования, HLS streaming
- **Push**: Web Push (Minishlink), Telegram notifications

## Frontend  
- **Tailwind CSS 3.4** - utility-first, dark mode support
- **Webpack Encore 4.7** - asset compilation
- **Stimulus 3.2** - JavaScript framework
- **Turbo 8.0** - SPA-like navigation
- **HTMX 2.0** - dynamic updates
- **Alpine.js 3.15** - reactive components
- **Chart.js** - analytics graphs
- **Quill 2.0** - rich text editor
- **Tom Select 2.2** - enhanced selects
- **Sortable.js** - drag & drop

## Development (Laragon)
- **Веб-сервер**: nginx 1.29.4
- **PHP**: 8.4.16 (NTS, VS17, x64)
  - Путь: `D:\laragon\bin\php\php-8.4.16-nts-Win32-vs17-x64\php.exe`
- **NodeJS**: v22 (`D:\laragon\bin\nodejs\node-v22`)
- **URL**: http://tubocms.test:8080/
- **Root**: /public
- **Документация**: https://laragon.org/docs

## Common Commands

### Backend
```bash
# Cache
php bin/console cache:clear
php bin/console cache:warmup

# Database
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# Video processing
php bin/console app:process-video {id}
php bin/console app:process-pending-videos
php bin/console messenger:consume async -vv

# Optimization
php bin/console app:optimize-database
php bin/console app:refresh-materialized-views

# Users & Roles
php bin/console app:create-admin
php bin/console app:init-roles-permissions
```

### Frontend
```bash
# Development
npm run dev
npm run watch

# Production
npm run build

# Или через bat-файлы
build-dev.bat
build-prod.bat
build-watch.bat
```

### Testing
```bash
# All tests (83 tests, 309 assertions)
php vendor/bin/phpunit

# Specific test suite
php vendor/bin/phpunit --testsuite "Property Tests"

# With coverage
php vendor/bin/phpunit --coverage-html coverage/
```

## Key Patterns
- **Repository Pattern** - data access через репозитории
- **Service Layer** - бизнес-логика в сервисах
- **Event-Driven** - EventListener/EventSubscriber
- **Message Queue** - асинхронная обработка через Messenger
- **Strategy Pattern** - адаптеры хранилищ
- **Voter Pattern** - проверка разрешений через PermissionVoter
- **Materialized Views** - оптимизация сложных запросов