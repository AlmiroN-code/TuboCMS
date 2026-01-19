# Быстрый старт: Хлебные крошки (Breadcrumbs)

## Что реализовано

✅ Визуальные breadcrumbs на всех основных страницах:
- Главная страница
- Список видео
- Детальная страница видео (Главная > Категория > Видео)
- Список категорий
- Отдельная категория (Главная > Категории > Название категории)
- Список моделей
- Страница модели (Главная > Модели > Имя модели)
- Профиль пользователя (Главная > Сообщество > Имя пользователя)
- Админ-панель (Dashboard > Раздел > Страница)

## Как добавить breadcrumbs на новую страницу

### Для фронтенда

В начале вашего шаблона добавьте:

```twig
{% extends 'base.html.twig' %}

{% set breadcrumbs = [
    {'label': 'breadcrumb.home'|trans, 'url': path('app_home')},
    {'label': 'Название раздела', 'url': path('route_name')},
    {'label': 'Текущая страница', 'url': ''}
] %}
```

### Для админ-панели

```twig
{% extends 'admin/base.html.twig' %}

{% set admin_breadcrumbs = [
    {'label': 'Dashboard', 'url': path('admin_dashboard')},
    {'label': 'Раздел', 'url': path('admin_section')},
    {'label': 'Текущая страница', 'url': ''}
] %}
```

## Важные правила

1. **Последний элемент** всегда без URL (пустая строка `''`)
2. **Используйте переводы** для статических элементов: `'breadcrumb.home'|trans`
3. **Динамические данные** (названия категорий, видео) - без перевода

## Примеры из проекта

### Страница видео
```twig
{% set breadcrumbs = [
    {'label': 'breadcrumb.home'|trans, 'url': path('app_home')},
    {'label': video.categories[0].name, 'url': path('app_category_show', {'slug': video.categories[0].slug})},
    {'label': video.title, 'url': ''}
] %}
```

### Страница категории
```twig
{% set breadcrumbs = [
    {'label': 'breadcrumb.home'|trans, 'url': path('app_home')},
    {'label': 'category.title'|trans, 'url': path('app_categories')},
    {'label': category.name, 'url': ''}
] %}
```

## Файлы системы

- `templates/partials/_breadcrumbs.html.twig` - партиал фронтенда
- `templates/admin/_breadcrumbs.html.twig` - партиал админки
- `translations/messages.ru.yaml` - переводы (секция `breadcrumb:`)

## Стилизация

Breadcrumbs автоматически адаптируются под:
- Светлую/темную тему
- Мобильные устройства
- Используют Tailwind CSS

## Тестирование

После добавления breadcrumbs:
1. Откройте страницу в браузере
2. Проверьте, что breadcrumbs отображаются
3. Проверьте, что все ссылки работают
4. Проверьте адаптивность на мобильных

## Поддержка

Полная документация: `docs/BREADCRUMBS.md`
