# Coding Standards

## PHP Standards
- **PHP 8.4** features: typed properties, constructor property promotion, attributes
- **Strict types**: `declare(strict_types=1);` в начале каждого файла
- **Doctrine attributes** (НЕ annotations): `#[ORM\Entity]`, `#[ORM\Column]`
- **Route attributes**: `#[Route('/path', name: 'route_name', methods: ['GET'])]`
- **Dependency injection** через конструктор (autowiring)
- **Naming**: camelCase методы, PascalCase классы
- **Return types**: всегда указывать типы возврата
- **Null safety**: использовать `?Type` для nullable

### Примеры
```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/videos', name: 'video_')]
class VideoController extends AbstractController
{
    public function __construct(
        private readonly VideoService $videoService,
        private readonly EntityManagerInterface $entityManager,
    ) {}
    
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Video $video): Response
    {
        return $this->render('video/show.html.twig', [
            'video' => $video,
        ]);
    }
}
```

## Frontend Standards  

### Tailwind CSS
- **Utility-first**: использовать только utility классы
- **NO custom CSS**: не создавать кастомные стили
- **Dark mode**: использовать `dark:` префикс
- **Responsive**: использовать `sm:`, `md:`, `lg:`, `xl:` префиксы
- **Theme variables**: использовать `theme-primary`, `theme-secondary` для цветов

### Proper Tailwind Classes
```html
<!-- Container -->
<div class="container mx-auto px-4">

<!-- Card -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">

<!-- Button Primary -->
<button class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md shadow-sm text-sm font-medium transition-colors">

<!-- Button Secondary -->
<button class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md text-sm font-medium transition-colors">

<!-- Input -->
<input class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors w-full">

<!-- Select -->
<select class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors w-full">

<!-- Checkbox -->
<input type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">

<!-- Badge -->
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
```

### Stimulus Controllers
- **Naming**: kebab-case (`video-player_controller.js`)
- **Targets**: использовать data-[controller]-target
- **Actions**: использовать data-action
- **Values**: использовать data-[controller]-[name]-value

```javascript
// assets/controllers/video-player_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['video', 'playButton'];
    static values = {
        autoplay: Boolean,
        url: String
    };
    
    connect() {
        if (this.autoplayValue) {
            this.play();
        }
    }
    
    play() {
        this.videoTarget.play();
    }
}
```

```html
<!-- Usage -->
<div data-controller="video-player" 
     data-video-player-autoplay-value="true"
     data-video-player-url-value="/videos/123.mp4">
    <video data-video-player-target="video"></video>
    <button data-action="click->video-player#play" 
            data-video-player-target="playButton">Play</button>
</div>
```

### Twig Templates
- **Semantic HTML**: использовать правильные теги (`<article>`, `<section>`, `<nav>`)
- **Accessibility**: aria-labels, alt для изображений, role атрибуты
- **Partials**: начинаются с `_` (underscore)
- **Blocks**: использовать для переопределения в child templates

```twig
{# templates/video/show.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ video.title }} - {{ parent() }}{% endblock %}

{% block body %}
<article class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-4">{{ video.title }}</h1>
    
    {# Include partial #}
    {% include 'video/_player.html.twig' %}
    
    {# Conditional rendering #}
    {% if is_granted('PERMISSION_video.edit', video) %}
        <a href="{{ path('video_edit', {id: video.id}) }}">Edit</a>
    {% endif %}
</article>
{% endblock %}
```

## Form Styling (Tailwind Classes)

### Input Fields
```html
<input type="text" 
       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white transition-colors w-full">
```

### Select
```html
<select class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors w-full">
```

### Checkbox
```html
<input type="checkbox" 
       class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
```

### Radio
```html
<input type="radio" 
       class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
```

### Textarea
```html
<textarea class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white transition-colors w-full resize-y"></textarea>
```

### Button Primary
```html
<button class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white border border-transparent rounded-md shadow-sm text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
```

### Button Secondary
```html
<button class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-md shadow-sm text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
```

### Button Danger
```html
<button class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white border border-transparent rounded-md shadow-sm text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
```

## Security

### CSRF Protection
- Всегда включать CSRF токены в формах
- Использовать `csrf_token()` в Twig
- Проверять токены в контроллерах

### Input Validation
- Использовать Symfony Validator
- Валидация на уровне Entity через attributes
- Кастомные валидаторы в `src/Validator/`

### Authentication & Authorization
- Form login через Security component
- RBAC: проверка через `#[IsGranted('PERMISSION_name')]`
- Voters для сложной логики прав

### Rate Limiting
- Настроено для медиа-файлов (100 req/hour)
- Использовать для API endpoints
- Конфигурация в `config/packages/rate_limiter.yaml`

### File Uploads
- Валидация типов файлов
- Ограничение размера
- Безопасные имена файлов
- Хранение вне public/ для приватных файлов

### Content Protection
- Hotlink protection (проверка Referer)
- User-Agent filtering (блокировка wget, curl, youtube-dl)
- Signed URLs с временными токенами
- Rate limiting для медиа

## Code Quality

### PSR Standards
- PSR-12 для code style
- PSR-4 для autoloading
- PSR-7 для HTTP messages (где применимо)

### Documentation
- PHPDoc для публичных методов
- Описание параметров и return types
- @throws для исключений

```php
/**
 * Process video encoding for multiple quality levels.
 *
 * @param Video $video The video entity to process
 * @param array<string> $qualities Quality levels (360p, 480p, 720p, 1080p)
 * @return bool True if processing started successfully
 * @throws \RuntimeException If FFmpeg is not available
 */
public function processVideo(Video $video, array $qualities): bool
{
    // Implementation
}
```

### Testing
- Unit tests для сервисов
- Functional tests для контроллеров
- Property-based tests где применимо
- 83 теста, 309 assertions - поддерживать покрытие