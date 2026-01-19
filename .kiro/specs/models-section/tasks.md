# План реализации: Раздел моделей

- [x] 1. Создание новых сущностей и миграций






  - [x] 1.1 Создать сущность ModelSubscription

    - Создать файл `src/Entity/ModelSubscription.php`
    - Определить связи с User и ModelProfile
    - Добавить уникальный индекс на пару user_id + model_id
    - _Requirements: 3.1, 3.2_

  - [x] 1.2 Создать сущность ModelLike

    - Создать файл `src/Entity/ModelLike.php`
    - Определить связи с User и ModelProfile
    - Добавить поле type (like/dislike)
    - Добавить уникальный индекс на пару user_id + model_id
    - _Requirements: 4.1, 4.2_
  - [x] 1.3 Добавить поле dislikesCount в ModelProfile


    - Обновить сущность ModelProfile
    - _Requirements: 4.2_

  - [x] 1.4 Создать репозитории для новых сущностей

    - Создать `src/Repository/ModelSubscriptionRepository.php`
    - Создать `src/Repository/ModelLikeRepository.php`
    - _Requirements: 3.1, 4.1_
  - [x] 1.5 Сгенерировать и применить миграцию


    - Выполнить `php bin/console make:migration`
    - Выполнить `php bin/console doctrine:migrations:migrate`
    - _Requirements: 3.1, 4.1_

- [x] 2. Расширение репозитория ModelProfileRepository





  - [x] 2.1 Добавить методы поиска и фильтрации


    - Метод `findPaginated(page, limit, search, sort, gender)`
    - Метод `findBySlug(slug)`
    - Метод `findActiveWithVideos(modelId, limit, offset)`
    - _Requirements: 1.1, 1.3, 1.4, 1.5, 2.4_
  - [x] 2.2 Написать property-тест для сортировки моделей


    - **Property 1: Сортировка моделей корректна**
    - **Validates: Requirements 1.3**
  - [x] 2.3 Написать property-тест для поиска моделей


    - **Property 2: Поиск моделей возвращает релевантные результаты**
    - **Validates: Requirements 1.4**

  - [x] 2.4 Написать property-тест для фильтрации по полу

    - **Property 3: Фильтрация по полу корректна**
    - **Validates: Requirements 1.5**

- [x] 3. Создание сервиса ModelStatsService




  - [x] 3.1 Создать сервис ModelStatsService


    - Создать файл `src/Service/ModelStatsService.php`
    - Метод `incrementViewCount(model, user, session)` с защитой от накрутки
    - Метод `getZodiacSign(birthDate)` для вычисления знака зодиака
    - Метод `calculateAge(birthDate)` для вычисления возраста
    - Метод `updateVideosCount(model)` для пересчёта видео
    - _Requirements: 5.1, 5.2, 2.2_
  - [x] 3.2 Написать property-тест для подсчёта просмотров


    - **Property 8: Просмотры корректно подсчитываются с защитой от накрутки**
    - **Validates: Requirements 5.1, 5.2**

- [x] 4. Создание публичного контроллера ModelController






  - [x] 4.1 Создать контроллер ModelController

    - Создать файл `src/Controller/ModelController.php`
    - Метод `index()` для списка моделей с пагинацией, сортировкой, поиском, фильтрацией
    - Метод `show()` для профиля модели с видео
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_
  - [x] 4.2 Написать property-тест для видео модели


    - **Property 4: Видео модели принадлежат этой модели**
    - **Validates: Requirements 2.4**

- [x] 5. Создание контроллера подписок ModelSubscriptionController






  - [x] 5.1 Создать контроллер ModelSubscriptionController

    - Создать файл `src/Controller/ModelSubscriptionController.php`
    - Метод `toggle()` для подписки/отписки через HTMX
    - Обновление счётчика subscribersCount
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  - [x] 5.2 Написать property-тест для подписок


    - **Property 5: Подписка корректно обновляет счётчик**
    - **Validates: Requirements 3.1, 3.2**
  - [x] 5.3 Написать property-тест для состояния кнопки подписки


    - **Property 6: Состояние кнопки подписки соответствует наличию подписки**
    - **Validates: Requirements 3.4**

- [x] 6. Создание контроллера лайков ModelLikeController




  - [x] 6.1 Создать контроллер ModelLikeController


    - Создать файл `src/Controller/ModelLikeController.php`
    - Метод `toggle()` для лайка/дизлайка через HTMX
    - Обновление счётчиков likesCount и dislikesCount
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  - [x] 6.2 Написать property-тест для лайков/дизлайков


    - **Property 7: Лайки/дизлайки корректно обновляют счётчики**
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4**

- [x] 7. Создание шаблонов для публичной части



  - [x] 7.1 Создать шаблон списка моделей

    - Создать файл `templates/model/index.html.twig`
    - Сетка карточек моделей с пагинацией
    - Форма поиска, сортировки и фильтрации
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 7.2 Создать шаблон карточки модели
    - Создать файл `templates/model/_card.html.twig`
    - Аватар, имя, статистика (видео, подписчики, просмотры)
    - _Requirements: 1.2_

  - [x] 7.3 Создать шаблон профиля модели
    - Создать файл `templates/model/show.html.twig`
    - Обложка, аватар, информация о модели
    - Все поля: пол, возраст, дата рождения, страна, этничность, начало карьеры, знак зодиака, цвет волос, цвет глаз, татуировки, пирсинг, формы, рост, вес
    - Статистика: просмотры, подписчики, видео, лайки
    - Сетка видео с пагинацией
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

  - [x] 7.4 Создать шаблон кнопки подписки на модель
    - Создать файл `templates/model/_subscribe_button.html.twig`
    - HTMX интеграция для обновления без перезагрузки
    - _Requirements: 3.4, 3.5_

  - [x] 7.5 Создать шаблон кнопок лайков модели

    - Создать файл `templates/model/_like_buttons.html.twig`
    - HTMX интеграция для обновления без перезагрузки
    - _Requirements: 4.6_

- [x] 8. Расширение админ-панели для моделей





  - [x] 8.1 Обновить форму редактирования модели в админке


    - Обновить `templates/admin/models/form.html.twig`
    - Добавить все поля: аватар, обложка, пол, возраст, дата рождения, страна, этничность, начало карьеры, цвет волос, цвет глаз, татуировки, пирсинг, размер груди, рост, вес, статусы
    - _Requirements: 6.1_


  - [x] 8.2 Обновить контроллер AdminModelController

    - Обновить `src/Controller/Admin/AdminModelController.php`
    - Обработка загрузки аватара и обложки
    - Автогенерация slug
    - Обновление updatedAt

    - _Requirements: 6.2, 6.3, 6.4_
  - [x] 8.3 Написать property-тест для генерации slug

    - **Property 9: Slug генерируется корректно**
    - **Validates: Requirements 6.3**

- [x] 9. Интеграция моделей с видео




  - [x] 9.1 Обновить форму редактирования видео в админке


    - Обновить `templates/admin/videos/form.html.twig`
    - Добавить выбор моделей-участников (multiple select)
    - _Requirements: 7.1_
  - [x] 9.2 Обновить контроллер AdminVideoController


    - Обновить `src/Controller/Admin/AdminVideoController.php`
    - Обработка привязки/отвязки моделей
    - Обновление счётчика videosCount у моделей
    - _Requirements: 7.2, 7.3_


  - [x] 9.3 Обновить карточку видео для отображения моделей







    - Обновить `templates/video/_card.html.twig`
    - Добавить список моделей-участников с ссылками

    - _Requirements: 7.4_

  - [x] 9.4 Написать property-тест для счётчика видео модели

    - **Property 10: Привязка/отвязка модели к видео обновляет счётчик**
    - **Validates: Requirements 7.2, 7.3**

- [x] 10. Навигация и SEO



  - [x] 10.1 Добавить ссылку на раздел моделей в меню
    - Обновить `templates/partials/_header.html.twig`

    - _Requirements: 8.1_
  - [x] 10.2 Добавить meta-теги для страниц моделей

    - Обновить шаблоны с корректными title, description, Open Graph
    - _Requirements: 8.2, 8.3, 8.4_

  - [x] 10.3 Написать property-тест для meta-тегов

    - **Property 11: Meta-теги профиля модели корректны**
    - **Validates: Requirements 8.2**

- [x] 11. Добавление переводов



  - [x] 11.1 Добавить переводы для раздела моделей

    - Обновить `translations/messages.en.yaml`
    - Обновить `translations/messages.ru.yaml`
    - Ключи: models.*, model.*
    - _Requirements: 1.1, 2.1_

- [x] 12. Checkpoint - Убедиться что все тесты проходят



  - Ensure all tests pass, ask the user if questions arise.
