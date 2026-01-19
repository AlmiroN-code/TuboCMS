# Система хлебных крошек (Breadcrumbs)

## Описание

Система breadcrumbs обеспечивает визуальную навигацию на всех страницах сайта, показывая пользователю его текущее местоположение в структуре сайта.

## Структура

### Файлы

- `templates/partials/_breadcrumbs.html.twig` - партиал для фронтенда
- `templates/admin/_breadcrumbs.html.twig` - партиал для админ-панели
- `src/Twig/BreadcrumbsExtension.php` - Twig расширение (опционально)

### Интеграция

Breadcrumbs автоматически отображаются на всех страницах через базовые шаблоны:
- `templates/base.html.twig` - для фронтенда
- `templates/admin/base.html.twig` - для админ-панели

## Использование

### В шаблонах фронтенда

Определите переменную `breadcrumbs` в начале шаблона:

```twig
{% extends 'base.html.twig' %}

{% set breadcrumbs = [
    {'label': 'breadcrumb.home'|trans, 'url': path('app_home')},
    {'label': 'category.title'|trans, 'url': path('app_categories')},
    {'label': category.name, 'url': ''}
] %}
```

### В шаблонах админ-панели

Используйте переменную `admin_breadcrumbs`:

```twig
{% extends 'admin/base.html.twig' %}

{% set admin_breadcrumbs = [
    {'label': 'Dashboard', 'url': path('admin_dashboard')},
    {'label': 'Категории', 'url': path('admin_categories')},
    {'label': 'Редактировать', 'url': ''}
] %}
```

## Примеры

### Главная страница
```
Главная
```

### Страница категории
```
Главная > Категории > Название категории
```

### Страница видео
```
Главная > Категория > Название видео
```

### Страница модели
```
Главная > Модели > Имя модели
```

### Профиль пользователя
```
Главная > Сообщество > Имя пользователя
```

### Админ-панель
```
Dashboard > Категории > Редактировать
```

## Правила

1. **Последний элемент** - всегда текущая страница (без ссылки)
2. **Первый элемент** - всегда "Главная" для фронтенда или "Dashboard" для админки
3. **Переводы** - используйте ключи переводов для статических элементов
4. **Динамические данные** - используйте реальные названия (категорий, видео, пользователей)

## Переводы

Переводы добавлены в файлы переводов:

### Русский (`translations/messages.ru.yaml`):
```yaml
breadcrumb:
    home: "Главная"
    videos: "Видео"
    categories: "Категории"
    models: "Модели"
    community: "Сообщество"
    admin: "Админ панель"
```

### Английский (`translations/messages.en.yaml`):
```yaml
breadcrumb:
    home: "Home"
    videos: "Videos"
    categories: "Categories"
    models: "Models"
    community: "Community"
    admin: "Admin Panel"
```

### Использование в шаблонах:
```twig
{'label': 'breadcrumb.home'|trans, 'url': path('app_home')}
```

## Стилизация

Breadcrumbs используют Tailwind CSS классы:
- Адаптивный дизайн
- Темная тема (dark mode)
- Hover эффекты
- Иконки-разделители (стрелки)

## Доступность

- Используется семантический тег `<nav>` с `aria-label="Breadcrumb"`
- Последний элемент имеет `aria-current="page"`
- Структурированные данные через `<ol>` список
