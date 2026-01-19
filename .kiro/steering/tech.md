# Tech Stack & Build System

## Backend

- **Framework**: Symfony 8.0 (PHP 8.4+)
- **Database**: MySQL 8.0 with Doctrine ORM 3.5
- **ORM**: Doctrine with migrations support
- **Validation**: Symfony Validator
- **Security**: Symfony Security Bundle with form-based authentication
- **Async Processing**: Symfony Messenger with Doctrine transport
- **Rate Limiting**: Symfony Rate Limiter
- **Email**: Symfony Mailer
- **Serialization**: Symfony Serializer

## Frontend

- **CSS Framework**: Tailwind CSS 3.4
- **Build Tool**: Webpack Encore 4.7
- **JavaScript**: Babel 7.26 with ES6+ support
- **Interactivity**: Stimulus 3.2 (Hotwired)
- **Navigation**: Turbo 8.0 (SPA-like navigation)
- **Templating**: Twig 3.22
- **CSS Processing**: PostCSS with Autoprefixer

## Development Tools

- **Package Manager**: Composer (PHP), npm (Node.js)
- **Web Server**: Laragon (Nginx 1.2.7) for local development
- **Database**: MySQL 8.0
- **Testing**: Doctrine Fixtures for test data

## Common Commands

### PHP/Symfony

```bash
# Clear cache
php bin/console cache:clear

# Database operations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load --no-interaction

# Messenger (async processing)
php bin/console messenger:setup-transports
php bin/console messenger:consume async -vv

# Generate migrations
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Create entities/forms
php bin/console make:entity
php bin/console make:form
```

### Frontend/Webpack

```bash
# Development build
npm run dev

# Watch mode (auto-rebuild on changes)
npm run watch

# Production build
npm run build

# Development server with hot reload
npm run dev-server
```

### Batch Files (Windows)

- `build-dev.bat` - One-time development build
- `build-watch.bat` - Watch mode for development
- `build-prod.bat` - Production build
- `clear-cache.bat` - Full cache clear and rebuild
- `setup-messenger.bat` - Initialize Messenger tables
- `messenger-worker.bat` - Run async job processor

## Key Configuration Files

- `config/services.yaml` - Service container configuration
- `config/packages/` - Bundle-specific configuration
- `webpack.config.js` - Webpack Encore configuration
- `tailwind.config.js` - Tailwind CSS configuration
- `postcss.config.js` - PostCSS configuration
- `.env` / `.env.dev` - Environment variables
- `composer.json` - PHP dependencies
- `package.json` - Node.js dependencies

## Project Structure

- `src/` - PHP application code (Controllers, Entities, Services, etc.)
- `config/` - Symfony configuration
- `templates/` - Twig templates
- `assets/` - Frontend source files (CSS, JS)
- `public/` - Web root (compiled assets, media uploads)
- `migrations/` - Database migrations
- `var/` - Cache and logs

## Important Notes

- PHP 8.4.15 required (strict types, modern features)
- Doctrine migrations are used for database versioning
- Messenger handles async video processing
- Webpack Encore compiles and optimizes frontend assets
- Tailwind CSS uses JIT compilation with PurgeCSS
- Turbo provides SPA-like experience without full SPA complexity
- Всегда пиши и отвечай на русском языке

## окружение разработки
- **Документация Symfony**: https://symfony.com/doc
- **Пакеты Symfony**: https://symfony.com/packages
- **Документация Laragon**: https://laragon.org/docs
- **Веб-сервер**: nginx 1.2.7
- **PHP**: 8.4.15
- **Путь**: `D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe`
- **NodeJS**: `D:\laragon\bin\nodejs\node-v22`
- **URL доступа**: http://rextube.test:8080/
- **Корневая папка сайта**: /public