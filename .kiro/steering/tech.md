# Tech Stack & Environment

## Backend
- **Symfony 8.0** (PHP 8.4+), Doctrine ORM 3.5, MySQL 8.0
- **Security**: Form auth, rate limiting, validation
- **Async**: Messenger with Doctrine transport

## Frontend  
- **Tailwind CSS 3.4**, Webpack Encore 4.7, Stimulus 3.2, Turbo 8.0

## Development Laragon (окружение разработки)
- Документация: https://laragon.org/docs
- **Веб-сервер**: nginx 1.2.7
- **PHP**: 8.4
  - Путь: `D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe`
- **NodeJS**: `D:\laragon\bin\nodejs\node-v22`
- **URL доступа**: http://tubocms.test:8080/
- **Корневая папка сайта**: /public

## Key Patterns
- Controllers in `src/Controller/`, Entities with Doctrine attributes
- Twig templates, Tailwind for styling, modern PHP 8.4 features