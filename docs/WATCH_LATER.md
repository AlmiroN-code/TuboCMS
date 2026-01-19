# Функционал "Смотреть позже" (Watch Later)

## Описание

Функционал "Смотреть позже" позволяет пользователям сохранять видео для просмотра в будущем. Это персональный список видео, доступный только авторизованным пользователям.

## Компоненты

### Backend

#### Entity
- **WatchLater** (`src/Entity/WatchLater.php`)
  - Связывает пользователя и видео
  - Хранит дату добавления
  - Уникальный индекс на пару user_id + video_id

#### Repository
- **WatchLaterRepository** (`src/Repository/WatchLaterRepository.php`)
  - `isInWatchLater()` - проверка наличия видео в списке
  - `addToWatchLater()` - добавление видео
  - `removeFromWatchLater()` - удаление видео
  - `findUserWatchLater()` - получение списка с пагинацией
  - `countUserWatchLater()` - подсчет количества
  - `getUserWatchLaterVideoIds()` - получение ID всех видео

#### Controller
- **WatchLaterController** (`src/Controller/WatchLaterController.php`)
  - `GET /watch-later` - страница со списком
  - `POST /watch-later/toggle/{id}` - добавление/удаление видео
  - `GET /watch-later/check/{id}` - проверка статуса (опционально)

#### Twig Extension
- **WatchLaterExtension** (`src/Twig/WatchLaterExtension.php`)
  - `is_in_watch_later(videoId)` - проверка в шаблонах
  - `watch_later_video_ids()` - получение всех ID для текущего пользователя

### Frontend

#### JavaScript
- **watch-later.js** (`assets/watch-later.js`)
  - `toggleWatchLater()` - переключение состояния
  - `updateWatchLaterButton()` - обновление UI кнопки
  - `checkWatchLaterStatus()` - проверка статуса при загрузке
  - `showNotification()` - показ уведомлений

#### Шаблоны
- **templates/watch_later/list.html.twig** - страница со списком
- **templates/video/_card.html.twig** - кнопка в карточке видео
- **templates/partials/_pagination.html.twig** - универсальная пагинация

### База данных

#### Таблица watch_later
```sql
CREATE TABLE watch_later (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE INDEX watch_later_user_video_unique (user_id, video_id),
    INDEX idx_watch_later_user (user_id),
    INDEX idx_watch_later_created (created_at),
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES video(id) ON DELETE CASCADE
);
```

## Использование

### Для пользователей

1. **Добавление видео**
   - Наведите на карточку видео
   - Нажмите на иконку часов в правом верхнем углу
   - Видео добавится в список "Смотреть позже"

2. **Просмотр списка**
   - Откройте меню профиля
   - Выберите "Смотреть позже"
   - Или перейдите по адресу `/watch-later`

3. **Удаление из списка**
   - На странице списка или на карточке видео
   - Нажмите на иконку часов повторно
   - Видео будет удалено из списка

### Для разработчиков

#### Проверка в Twig
```twig
{% if is_in_watch_later(video.id) %}
    {# Видео в списке #}
{% endif %}
```

#### Получение всех ID
```twig
{% set watchLaterIds = watch_later_video_ids() %}
```

#### AJAX запрос
```javascript
// Переключение состояния
await fetch(`/watch-later/toggle/${videoId}`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    }
});
```

## Переводы

### Русский (messages.ru.yaml)
```yaml
watch_later:
    title: "Смотреть позже"
    description: "У вас %count% видео в списке"
    add: "Добавить в \"Смотреть позже\""
    remove: "Удалить из \"Смотреть позже\""
    empty:
        title: "Список \"Смотреть позже\" пуст"
        description: "Добавляйте видео в этот список, чтобы посмотреть их позже"
        browse_videos: "Смотреть видео"
```

### Английский (messages.en.yaml)
```yaml
watch_later:
    title: "Watch Later"
    description: "You have %count% videos in the list"
    add: "Add to Watch Later"
    remove: "Remove from Watch Later"
    empty:
        title: "Watch Later list is empty"
        description: "Add videos to this list to watch them later"
        browse_videos: "Browse videos"
```

## Особенности реализации

1. **Оптимизация производительности**
   - ID видео передаются через data-атрибут в body
   - Избегаем множественных AJAX-запросов при загрузке страницы
   - Используем индексы БД для быстрых запросов

2. **UX**
   - Кнопка появляется при наведении на карточку
   - Визуальная обратная связь (изменение цвета)
   - Уведомления об успешных действиях
   - Поддержка Turbo для SPA-навигации

3. **Безопасность**
   - Требуется авторизация (IsGranted)
   - CASCADE удаление при удалении пользователя/видео
   - Уникальный индекс предотвращает дубликаты

## Миграция

Миграция создана автоматически:
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

Файл: `migrations/Version20260114090113.php`

## Тестирование

1. Авторизуйтесь на сайте
2. Откройте любую страницу с видео
3. Наведите на карточку видео
4. Нажмите на иконку часов
5. Проверьте, что видео добавилось (иконка изменила цвет)
6. Откройте `/watch-later`
7. Убедитесь, что видео отображается в списке
8. Удалите видео из списка
9. Проверьте, что оно исчезло

## Возможные улучшения

- [ ] Добавить сортировку списка
- [ ] Добавить фильтрацию по категориям
- [ ] Добавить массовое удаление
- [ ] Добавить экспорт списка
- [ ] Добавить уведомления о новых видео в списке
- [ ] Добавить автоматическое удаление просмотренных видео
