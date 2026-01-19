# Руководство по системе прав доступа RexTube

## Обзор

RexTube использует гибкую систему прав доступа на основе ролей (RBAC - Role-Based Access Control), полностью интегрированную с Symfony Security.

## Архитектура

### Компоненты системы

1. **Permission (Право)** - атомарное разрешение на выполнение действия
2. **Role (Роль)** - набор прав, назначаемый пользователям
3. **User (Пользователь)** - имеет одну или несколько ролей
4. **PermissionVoter** - интеграция с Symfony Security

## Базовые роли

### ROLE_USER (Пользователь)
Обычный пользователь сайта с базовыми правами:
- Просмотр контента
- Создание своих видео
- Комментирование
- Редактирование/удаление своего контента

**Права:** 11 базовых прав

### ROLE_MODERATOR (Модератор)
Модератор контента с расширенными правами:
- Все права пользователя
- Модерация видео
- Управление комментариями (редактирование/удаление любых)
- Блокировка пользователей
- Доступ к админ-панели

**Права:** 18 прав

### ROLE_ADMIN (Администратор)
Полный доступ ко всем функциям системы:
- Все права модератора
- Управление пользователями
- Управление категориями, тегами, моделями
- Управление настройками сайта
- Управление ролями и правами
- Системные настройки

**Права:** Все 39 прав

## Категории прав

### Видео (video)
- `video.view` - Просмотр видео
- `video.create` - Создание видео
- `video.edit` - Редактирование своих видео
- `video.edit_all` - Редактирование любых видео
- `video.delete` - Удаление своих видео
- `video.delete_all` - Удаление любых видео
- `video.moderate` - Модерация видео

### Пользователи (user)
- `user.view` - Просмотр пользователей
- `user.create` - Создание пользователей
- `user.edit` - Редактирование пользователей
- `user.delete` - Удаление пользователей
- `user.ban` - Блокировка пользователей

### Комментарии (comment)
- `comment.view` - Просмотр комментариев
- `comment.create` - Создание комментариев
- `comment.edit` - Редактирование своих комментариев
- `comment.edit_all` - Редактирование любых комментариев
- `comment.delete` - Удаление своих комментариев
- `comment.delete_all` - Удаление любых комментариев

### Категории (category)
- `category.view` - Просмотр категорий
- `category.create` - Создание категорий
- `category.edit` - Редактирование категорий
- `category.delete` - Удаление категорий

### Теги (tag)
- `tag.view` - Просмотр тегов
- `tag.create` - Создание тегов
- `tag.edit` - Редактирование тегов
- `tag.delete` - Удаление тегов

### Модели (model)
- `model.view` - Просмотр моделей
- `model.create` - Создание моделей
- `model.edit` - Редактирование моделей
- `model.delete` - Удаление моделей

### Настройки (settings)
- `settings.view` - Просмотр настроек
- `settings.edit` - Редактирование настроек

### Роли (role)
- `role.view` - Просмотр ролей
- `role.create` - Создание ролей
- `role.edit` - Редактирование ролей
- `role.delete` - Удаление ролей

### Админ-панель (admin)
- `admin.access` - Доступ к админ-панели
- `admin.dashboard` - Просмотр дашборда
- `admin.system` - Системные настройки

## Использование в коде

### В контроллерах

#### Проверка роли
```php
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // Весь контроллер доступен только администраторам
}
```

#### Проверка права
```php
#[IsGranted('PERMISSION_video.moderate')]
public function moderate(): Response
{
    // Доступно только пользователям с правом video.moderate
}
```

#### Программная проверка
```php
public function someAction(): Response
{
    // Проверка роли
    if (!$this->isGranted('ROLE_MODERATOR')) {
        throw $this->createAccessDeniedException();
    }
    
    // Проверка права
    if (!$this->isGranted('PERMISSION_video.edit_all')) {
        throw $this->createAccessDeniedException();
    }
}
```

### В шаблонах Twig

```twig
{# Проверка роли #}
{% if is_granted('ROLE_ADMIN') %}
    <a href="{{ path('admin_dashboard') }}">Админ-панель</a>
{% endif %}

{# Проверка права #}
{% if is_granted('PERMISSION_video.moderate') %}
    <button>Модерировать</button>
{% endif %}
```

### В Entity User

```php
// Проверка роли
if ($user->hasRole('ROLE_ADMIN')) {
    // Пользователь - администратор
}

// Проверка права
if ($user->hasPermission('video.moderate')) {
    // Пользователь может модерировать видео
}
```

### В security.yaml

```yaml
access_control:
    # Проверка роли
    - { path: ^/admin, roles: ROLE_ADMIN }
    
    # Проверка права
    - { path: ^/admin/moderate, roles: PERMISSION_video.moderate }
    
    # Проверка роли ИЛИ права
    - { path: ^/admin/videos, roles: [ROLE_ADMIN, PERMISSION_video.edit_all] }
```

## Команды управления

### Инициализация прав и ролей
```bash
php bin/console app:init-permissions
```
Создаёт все базовые права и роли. Можно запускать повторно для обновления.

### Назначение роли администратора
```bash
php bin/console app:assign-admin-role
```
Назначает роль ROLE_ADMIN пользователям, у которых есть ROLE_ADMIN в массиве roles.

## Управление через админ-панель

### Просмотр прав
`/admin/permissions` - список всех прав доступа с возможностью редактирования

### Управление ролями
`/admin/roles` - список ролей с назначенными правами

### Назначение ролей пользователям
`/admin/users/{id}/edit` - редактирование пользователя с выбором ролей

## Расширение системы

### Добавление нового права

1. Отредактируйте `src/Command/InitPermissionsCommand.php`
2. Добавьте новое право в массив `$permissions`
3. Запустите команду: `php bin/console app:init-permissions`

Пример:
```php
[
    'name' => 'blog.create',
    'displayName' => 'Создание блог-постов',
    'description' => 'Создание новых записей в блоге',
    'category' => 'blog'
]
```

### Создание новой роли

1. Отредактируйте `src/Command/InitPermissionsCommand.php`
2. Добавьте новую роль в массив `$roles`
3. Укажите права для роли
4. Запустите команду: `php bin/console app:init-permissions`

Пример:
```php
[
    'name' => 'ROLE_EDITOR',
    'displayName' => 'Редактор',
    'description' => 'Редактор контента',
    'permissions' => [
        'video.view', 'video.edit_all',
        'blog.create', 'blog.edit',
    ]
]
```

## Безопасность

### Рекомендации

1. **Минимальные права** - назначайте только необходимые права
2. **Регулярный аудит** - проверяйте назначенные роли и права
3. **Разделение обязанностей** - не давайте одному пользователю слишком много прав
4. **Логирование** - отслеживайте действия пользователей с повышенными правами

### Проверка прав в коде

Всегда проверяйте права перед выполнением критичных операций:

```php
// ❌ Плохо - нет проверки
public function deleteVideo(Video $video): Response
{
    $this->entityManager->remove($video);
    $this->entityManager->flush();
}

// ✅ Хорошо - с проверкой
#[IsGranted('PERMISSION_video.delete_all')]
public function deleteVideo(Video $video): Response
{
    $this->entityManager->remove($video);
    $this->entityManager->flush();
}
```

## Troubleshooting

### Права не работают

1. Очистите кэш: `php bin/console cache:clear`
2. Проверьте, назначена ли роль пользователю
3. Проверьте, активна ли роль (`isActive = true`)
4. Проверьте, активно ли право (`isActive = true`)

### Пользователь не может войти в админ-панель

1. Проверьте роли пользователя: `$user->getRoles()`
2. Назначьте роль: `php bin/console app:assign-admin-role`
3. Проверьте `security.yaml` - правильно ли настроен `access_control`

### Новые права не появляются

1. Запустите: `php bin/console app:init-permissions`
2. Очистите кэш: `php bin/console cache:clear`
3. Проверьте базу данных: таблицы `permission`, `role`, `role_permission`

## Заключение

Система прав RexTube обеспечивает гибкое управление доступом с полной интеграцией в Symfony Security. Используйте её для создания безопасного и масштабируемого приложения.
