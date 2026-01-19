# Система всплывающих уведомлений (Toast Notifications)

## Описание

Универсальная система всплывающих уведомлений для всего сайта. Показывает красивые анимированные уведомления в правом верхнем углу экрана при различных действиях пользователя.

## Компоненты

### Frontend

#### NotificationManager (`assets/js/notifications.js`)

Основной класс для управления уведомлениями.

**Методы:**
- `show(message, type, duration)` - показать уведомление
- `success(message, duration)` - показать успешное уведомление (зеленое)
- `error(message, duration)` - показать ошибку (красное)
- `warning(message, duration)` - показать предупреждение (желтое)
- `info(message, duration)` - показать информацию (синее)

**Глобальные функции:**
```javascript
window.showNotification(message, type, duration)
window.showSuccess(message, duration)
window.showError(message, duration)
window.showWarning(message, duration)
window.showInfo(message, duration)
```

#### Interactions (`assets/js/interactions.js`)

Глобальные функции для взаимодействия с контентом:
- `toggleBookmark(videoId)` - переключение закладки
- `toggleCardBookmark(button, videoId)` - закладка в карточке
- `openPlaylistModal(videoId)` - открыть модальное окно плейлиста
- `addToPlaylist(playlistId, videoId)` - добавить в плейлист
- `removeFromPlaylist(playlistId, videoId)` - удалить из плейлиста
- `handleSubscriptionSuccess(event)` - обработка подписки
- `voteVideo(videoId, type)` - голосование за видео
- `rateModel(modelId, rating)` - оценка модели

### Backend

#### Контроллеры с поддержкой уведомлений

1. **WatchLaterController**
   - `POST /watch-later/toggle/{id}` - добавить/удалить из "Смотреть позже"

2. **BookmarkController**
   - `POST /bookmarks/video/{id}` - добавить/удалить закладку

3. **PlaylistController**
   - `POST /playlists/{id}/videos/{videoId}` - добавить в плейлист
   - `DELETE /playlists/{id}/videos/{videoId}` - удалить из плейлиста

4. **SubscriptionController**
   - `POST /subscription/toggle/{id}` - подписаться/отписаться

Все контроллеры возвращают JSON с полем `message`, содержащим переведенное сообщение.

### Переводы

#### Русский (`translations/messages.ru.yaml`)
```yaml
toast:
    # Watch Later
    watch_later_added: "Видео добавлено в \"Смотреть позже\""
    watch_later_removed: "Видео удалено из \"Смотреть позже\""
    
    # Bookmarks
    bookmark_added: "Добавлено в избранное"
    bookmark_removed: "Удалено из избранного"
    bookmark_error: "Не удалось обновить закладку"
    
    # Playlists
    playlist_added: "Видео добавлено в плейлист"
    playlist_removed: "Видео удалено из плейлиста"
    playlist_error: "Не удалось добавить в плейлист"
    playlist_remove_error: "Не удалось удалить видео"
    
    # Subscriptions
    subscribed: "Вы подписались на канал"
    unsubscribed: "Вы отписались от канала"
    
    # Ratings/Votes
    vote_success: "Ваш голос учтён"
    vote_error: "Не удалось проголосовать"
    rating_success: "Спасибо за вашу оценку!"
    rating_error: "Не удалось поставить оценку"
    
    # Generic
    success: "Операция выполнена успешно"
    error: "Произошла ошибка"
    error_generic: "Произошла ошибка при обработке запроса"
```

#### Английский (`translations/messages.en.yaml`)
Аналогичная структура с английскими переводами.

## Использование

### В JavaScript

#### Простое уведомление
```javascript
window.showSuccess('Операция выполнена успешно');
window.showError('Произошла ошибка');
window.showWarning('Внимание!');
window.showInfo('Информация');
```

#### С настройкой длительности
```javascript
window.showSuccess('Сообщение', 5000); // 5 секунд
window.showError('Ошибка', 0); // Бесконечно (нужно закрыть вручную)
```

#### Через NotificationManager
```javascript
window.notificationManager.show('Сообщение', 'success', 3000);
```

### В контроллерах Symfony

```php
use Symfony\Contracts\Translation\TranslatorInterface;

public function someAction(TranslatorInterface $translator): JsonResponse
{
    // ... ваш код ...
    
    $message = $translator->trans('toast.success', [], 'messages');
    
    return $this->json([
        'success' => true,
        'message' => $message,
    ]);
}
```

### В шаблонах (через HTMX)

```twig
<div id="some-element" data-message="{{ message }}">
    {# Контент #}
</div>
```

```javascript
// JavaScript обработчик
document.addEventListener('htmx:afterSwap', function(event) {
    const message = event.detail.target.dataset.message;
    if (message) {
        window.showSuccess(message);
    }
});
```

## Типы уведомлений

### Success (Успех)
- **Цвет**: Зеленый
- **Иконка**: Галочка
- **Использование**: Успешные операции (добавление, сохранение, подписка)
- **Длительность по умолчанию**: 3 секунды

### Error (Ошибка)
- **Цвет**: Красный
- **Иконка**: Крестик
- **Использование**: Ошибки и неудачные операции
- **Длительность по умолчанию**: 4 секунды

### Warning (Предупреждение)
- **Цвет**: Желтый
- **Иконка**: Треугольник с восклицательным знаком
- **Использование**: Предупреждения и важная информация
- **Длительность по умолчанию**: 3.5 секунды

### Info (Информация)
- **Цвет**: Синий
- **Иконка**: Круг с буквой i
- **Использование**: Информационные сообщения
- **Длительность по умолчанию**: 3 секунды

## Особенности реализации

### Анимация
- Плавное появление справа (slide-in)
- Плавное исчезновение (fade-out + slide-out)
- Transition duration: 300ms

### Позиционирование
- Фиксированная позиция в правом верхнем углу
- z-index: 50 (выше большинства элементов)
- Отступ от края: 1rem (16px)

### Стек уведомлений
- Уведомления располагаются вертикально
- Новые уведомления добавляются снизу
- Автоматическое удаление после истечения времени
- Возможность закрыть вручную кнопкой

### Адаптивность
- Максимальная ширина: max-w-md (28rem)
- Адаптируется под размер экрана
- Корректно работает на мобильных устройствах

### Доступность
- Кнопка закрытия с hover эффектом
- Семантические SVG иконки
- Читаемый контраст цветов

## Интеграция с существующими функциями

### Watch Later
```javascript
window.toggleWatchLater(button, videoId)
// Автоматически показывает уведомление
```

### Bookmarks
```javascript
window.toggleBookmark(videoId)
window.toggleCardBookmark(button, videoId)
// Автоматически показывает уведомление
```

### Playlists
```javascript
window.addToPlaylist(playlistId, videoId)
window.removeFromPlaylist(playlistId, videoId)
// Автоматически показывает уведомление
```

### Subscriptions
```html
<!-- HTMX автоматически обрабатывает -->
<button hx-post="/subscription/toggle/1">Subscribe</button>
```

## Расширение системы

### Добавление нового типа уведомления

1. Добавьте CSS классы в `NotificationManager.getTypeClasses()`:
```javascript
getTypeClasses(type) {
    const classes = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        custom: 'bg-purple-500 text-white', // Новый тип
    };
    return classes[type] || classes.info;
}
```

2. Добавьте иконку в `NotificationManager.getIcon()`:
```javascript
getIcon(type) {
    const icons = {
        // ... существующие иконки
        custom: `<svg>...</svg>`, // Новая иконка
    };
    return icons[type] || icons.info;
}
```

3. Добавьте удобный метод:
```javascript
custom(message, duration = 3000) {
    return this.show(message, 'custom', duration);
}
```

### Добавление новых переводов

1. Добавьте ключ в `translations/messages.ru.yaml`:
```yaml
toast:
    my_new_message: "Моё новое сообщение"
```

2. Добавьте в `translations/messages.en.yaml`:
```yaml
toast:
    my_new_message: "My new message"
```

3. Используйте в контроллере:
```php
$message = $translator->trans('toast.my_new_message', [], 'messages');
```

## Тестирование

### Ручное тестирование
Откройте консоль браузера и выполните:
```javascript
window.showSuccess('Тест успеха');
window.showError('Тест ошибки');
window.showWarning('Тест предупреждения');
window.showInfo('Тест информации');
```

### Проверка интеграции
1. Добавьте видео в "Смотреть позже" - должно появиться зеленое уведомление
2. Добавьте в закладки - должно появиться зеленое уведомление
3. Подпишитесь на канал - должно появиться зеленое уведомление
4. Попробуйте вызвать ошибку - должно появиться красное уведомление

## Производительность

- Легковесная реализация (< 5KB минифицированного JS)
- Нет зависимостей от внешних библиотек
- Использует нативные Web APIs
- Оптимизированные CSS transitions
- Автоматическая очистка DOM после удаления уведомлений

## Совместимость

- Современные браузеры (Chrome, Firefox, Safari, Edge)
- Поддержка темной темы через Tailwind CSS
- Работает с Turbo (SPA-навигация)
- Совместимо с HTMX

## Будущие улучшения

- [ ] Звуковые уведомления (опционально)
- [ ] Группировка похожих уведомлений
- [ ] Позиционирование (верх/низ, лево/право)
- [ ] Прогресс-бар для длительных операций
- [ ] История уведомлений
- [ ] Настройки пользователя (отключение звука, позиция)
