# Project Structure

## Key Directories
- `src/` - PHP code (Controllers, Entities, Services, Repositories)
- `templates/` - Twig templates (admin/, partials/, video/, etc.)
- `assets/` - Frontend source (CSS, JS, Stimulus controllers)
- `public/media/` - User uploads (videos, avatars, posters)
- `config/` - Symfony configuration
- `migrations/` - Database migrations

## Naming Conventions
- **Classes**: PascalCase, **Methods**: camelCase
- **Templates**: snake_case.html.twig
- **Routes**: kebab-case URLs
- **DB tables**: snake_case (auto-generated)

## Architecture Patterns
- Controllers handle HTTP, inject services
- Entities define DB schema with Doctrine attributes  
- Services contain business logic
- Repositories handle queries
- Templates organized by feature, partials with `_`

## Laragon Environment
- nginx 1.2.7, PHP 8.4.15, NodeJS v22
- URL: http://rextube.test:8080/
- Root: /public