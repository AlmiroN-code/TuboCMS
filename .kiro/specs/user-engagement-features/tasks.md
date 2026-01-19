# Implementation Plan: User Engagement Features

## Overview

Данный план описывает пошаговую реализацию 13 функций пользовательского взаимодействия для RexTube. Реализация разбита на логические группы с инкрементальным подходом, где каждая задача строится на предыдущих. Используется существующая архитектура Symfony 8.0 с расширением новыми сущностями, сервисами и контроллерами.

## Tasks

- [x] 1. Настройка базовой инфраструктуры для новых функций
  - Создание миграций базы данных для всех новых сущностей
  - Настройка Doctrine сущностей и репозиториев
  - Обновление конфигурации Symfony Messenger для новых сообщений
  - _Requirements: 1.1-13.5_

- [x] 1.1 Создание property-based тестов для базовых сущностей

  - **Property 1: Playlist Creation Consistency**
  - **Property 44: Video Series Creation**
  - **Validates: Requirements 1.1, 12.1**

- [x] 2. Реализация системы плейлистов
  - [x] 2.1 Создание Playlist и PlaylistItem сущностей
    - Реализация сущностей с валидацией и связями
    - Создание репозиториев с методами поиска и сортировки
    - _Requirements: 1.1, 1.2, 1.5_

  - [x] 2.2 Реализация PlaylistService
    - Методы создания, редактирования и удаления плейлистов
    - Логика добавления/удаления видео и изменения порядка
    - Проверка прав доступа для приватных плейлистов
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [ ]* 2.3 Property-based тесты для плейлистов
    - **Property 2: Playlist Video Addition Order**
    - **Property 3: Playlist Reordering Consistency**
    - **Property 4: Playlist Video Removal Safety**
    - **Property 5: Playlist Privacy Enforcement**
    - **Validates: Requirements 1.2, 1.3, 1.4, 1.5**

  - [x] 2.4 Создание PlaylistController
    - CRUD операции для плейлистов
    - API endpoints для AJAX операций (добавление/удаление видео)
    - Обработка drag & drop для изменения порядка
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [ ]* 2.5 Unit тесты для PlaylistController
    - Тестирование HTTP endpoints и обработки ошибок
    - Проверка авторизации и валидации данных
    - _Requirements: 1.1-1.5_

- [x] 3. Реализация истории просмотров и рекомендаций
  - [x] 3.1 Создание WatchHistory сущности и сервиса
    - Сущность для хранения истории с уникальными ограничениями
    - WatchHistoryService с методами записи и получения истории
    - Логика обновления существующих записей при повторном просмотре
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [ ]* 3.2 Property-based тесты для истории просмотров
    - **Property 6: Watch History Recording Threshold**
    - **Property 7: Watch History Chronological Order**
    - **Property 8: Watch History Selective Removal**
    - **Property 9: Watch History Complete Clearing**
    - **Property 10: Watch History Update on Rewatch**
    - **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5**

  - [x] 3.3 Реализация базового алгоритма рекомендаций
    - RecommendationService с content-based фильтрацией
    - UserPreference сущность для хранения предпочтений пользователей
    - Алгоритм подбора видео по категориям, тегам и авторам
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [ ]* 3.4 Property-based тесты для рекомендаций
    - **Property 22: Personalized Recommendations Based on History**
    - **Property 23: Fallback Recommendations for New Users**
    - **Property 24: User Preferences Update on Watch**
    - **Validates: Requirements 6.1, 6.2, 6.3, 6.4**

- [x] 4. Checkpoint - Базовая функциональность работает
  - Убедиться что все тесты проходят, спросить пользователя если возникают вопросы

- [x] 5. Реализация системы оценок (лайки/дизлайки)
  - [x] 5.1 Создание VideoRating сущности и сервиса
    - Сущность с уникальными ограничениями пользователь-видео
    - VideoRatingService с методами постановки и изменения оценок
    - Обновление счетчиков лайков/дизлайков в Video сущности
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [ ]* 5.2 Property-based тесты для системы оценок
    - **Property 11: Video Rating Like Increment**
    - **Property 12: Video Rating Dislike Increment**
    - **Property 13: Video Rating Change Consistency**
    - **Property 14: Video Rating Cancellation**
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4**

  - [x] 5.3 Создание AJAX endpoints для оценок
    - VideoController методы для лайков/дизлайков
    - JSON ответы с обновленными счетчиками
    - Обработка неавторизованных пользователей
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 6. Реализация системы подписок и уведомлений
  - [x] 6.1 Создание Subscription сущности и сервиса
    - Сущность подписок с настройками уведомлений
    - SubscriptionService с методами подписки/отписки
    - Обновление счетчика подписчиков в User сущности
    - _Requirements: 4.1, 4.2, 4.5_

  - [ ]* 6.2 Property-based тесты для подписок
    - **Property 15: Subscription Creation and Counter Update**
    - **Property 16: Subscription Removal and Counter Update**
    - **Property 18: Subscription Feed Content**
    - **Validates: Requirements 4.1, 4.2, 4.4**

  - [x] 6.3 Реализация системы уведомлений
    - NotificationMessage и NotificationMessageHandler
    - Интеграция с Symfony Messenger для асинхронной отправки
    - Email уведомления при публикации новых видео
    - _Requirements: 4.3_

  - [ ]* 6.4 Property-based тест для уведомлений
    - **Property 17: New Video Notification to Subscribers**
    - **Validates: Requirements 4.3**

- [x] 7. Реализация закладок (избранное)
  - [x] 7.1 Создание Bookmark сущности и сервиса
    - Простая сущность связи пользователь-видео
    - BookmarkService с методами добавления/удаления
    - Интеграция с VideoController для AJAX операций
    - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 7.2 Property-based тесты для закладок
    - **Property 19: Bookmark Addition**
    - **Property 20: Bookmark Removal**
    - **Property 21: Bookmark Chronological Sorting**
    - **Validates: Requirements 5.1, 5.2, 5.3**

- [x] 8. Реализация расширенной фильтрации и сортировки
  - [x] 8.1 Расширение VideoRepository методами фильтрации
    - Методы фильтрации по длительности, дате, популярности
    - Сложные запросы с множественными фильтрами
    - Оптимизация запросов с индексами
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

  - [ ]* 8.2 Property-based тесты для фильтрации
    - **Property 25: Video Duration Filtering**
    - **Property 26: Video Date Sorting**
    - **Property 27: Video Popularity Sorting**
    - **Property 28: Video Rating Sorting**
    - **Property 29: Multiple Filters Combination**
    - **Property 30: Filter Reset to Default**
    - **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6**

  - [x] 8.3 Обновление UI для фильтров
    - Stimulus контроллер для управления фильтрами
    - AJAX обновление результатов без перезагрузки страницы
    - Сохранение состояния фильтров в URL
    - _Requirements: 7.1-7.6_

- [x] 9. Checkpoint - Основные функции реализованы
  - Базовая функциональность плейлистов, истории, лайков, подписок, закладок и фильтрации работает

- [x] 10. Реализация улучшенных комментариев
  - [x] 10.1 Расширение Comment сущности для вложенности
    - Добавление parent_id для иерархии комментариев (уже существует)
    - Методы для работы с упоминаниями (@username)
    - Обновление CommentRepository для получения дерева комментариев
    - _Requirements: 8.1, 8.2, 8.4_

  - [ ]* 10.2 Property-based тесты для комментариев
    - **Property 31: Nested Comment Creation and Notification**
    - **Property 32: Mention Notification**
    - **Property 33: Mention Link Rendering**
    - **Validates: Requirements 8.1, 8.2, 8.4**

  - [x] 10.3 Обновление CommentController
    - Обработка вложенных комментариев
    - Парсинг упоминаний и отправка уведомлений
    - AJAX endpoints для динамического добавления ответов
    - _Requirements: 8.1, 8.2, 8.4_

- [x] 11. Реализация профилей пользователей
  - [x] 11.1 Расширение User сущности
    - Добавление полей avatar, biography, subscribers_count (уже существует)
    - Валидация загружаемых аватаров
    - Методы для обработки изображений (сжатие, изменение размера)
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [ ]* 11.2 Property-based тесты для профилей
    - **Property 36: Avatar Upload and Storage**
    - **Property 37: Biography Update**
    - **Property 38: Profile Data Retrieval**
    - **Property 39: Avatar Format Validation**
    - **Property 40: Avatar Size Processing**
    - **Validates: Requirements 10.1, 10.2, 10.3, 10.4, 10.5**

  - [x] 11.3 Создание UserProfileController
    - Страницы просмотра и редактирования профиля (MembersController + ProfileController)
    - Загрузка и обработка аватаров (ImageService)
    - Валидация и сохранение биографии
    - _Requirements: 10.1, 10.2, 10.3_

- [x] 12. Реализация серий и сезонов
  - [x] 12.1 Создание VideoSeries, VideoSeason, VideoEpisode сущностей
    - Иерархическая структура серия -> сезон -> эпизод (уже существует)
    - Связи с Video сущностью и User (создатель)
    - Методы для навигации по эпизодам
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

  - [ ]* 12.2 Property-based тесты для серий
    - **Property 45: Video Episode Assignment**
    - **Property 46: Season Creation in Series**
    - **Property 47: Series Content Ordering**
    - **Property 48: Series Navigation**
    - **Property 49: Series Autoplay Suggestion**
    - **Validates: Requirements 12.2, 12.3, 12.4, 12.5, 12.6**

  - [x] 12.3 Создание VideoSeriesController
    - CRUD операции для серий и сезонов (SeriesController)
    - Страницы просмотра серий с навигацией по эпизодам
    - Интеграция с VideoController для автовоспроизведения (SeriesService.getNextEpisode)
    - _Requirements: 12.1-12.6_

- [x] 13. Реализация связанных видео и шаринга
  - [x] 13.1 Расширение VideoService алгоритмом связанных видео
    - Алгоритм поиска по общим тегам, категориям, авторам (RecommendationService.getRelatedVideos)
    - Приоритизация видео из той же серии
    - Fallback к популярным видео из категории
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

  - [ ]* 13.2 Property-based тесты для связанных видео
    - **Property 41: Related Videos Algorithm**
    - **Property 42: Series Videos Priority**
    - **Property 43: Related Videos Fallback**
    - **Validates: Requirements 11.1, 11.2, 11.3, 11.4**

  - [x] 13.3 Реализация функций шаринга
    - ShareService для генерации ссылок социальных сетей
    - Open Graph метатеги в шаблонах (ShareExtension)
    - JavaScript для копирования ссылок в буфер обмена (share_controller.js)
    - _Requirements: 9.2, 9.4_

  - [ ]* 13.4 Property-based тесты для шаринга
    - **Property 34: Social Share URL Generation**
    - **Property 35: Open Graph Meta Tags Generation**
    - **Validates: Requirements 9.2, 9.4_

- [x] 14. Реализация анимированных превью
  - [x] 14.1 Расширение VideoProcessingService
    - Интеграция с FFmpeg для генерации WebP/GIF превью
    - GeneratePreviewMessage и GeneratePreviewMessageHandler
    - Асинхронная обработка через Symfony Messenger
    - _Requirements: 13.3, 13.4_

  - [ ]* 14.2 Property-based тесты для превью
    - **Property 50: Animated Preview Generation**
    - **Property 51: Preview Fallback to Static**
    - **Validates: Requirements 13.3, 13.4**

  - [x] 14.3 Frontend для анимированных превью
    - Stimulus контроллер для hover эффектов (video_preview_controller.js)
    - Детекция мобильных устройств и отключение превью
    - Lazy loading и оптимизация производительности
    - _Requirements: 13.5_

  - [ ]* 14.4 Property-based тест для мобильной оптимизации
    - **Property 52: Mobile Preview Optimization**
    - **Validates: Requirements 13.5**

- [x] 15. Интеграция и финальная настройка
  - [x] 15.1 Обновление существующих шаблонов
    - Интеграция новых функций в существующие страницы (уже интегрировано)
    - Обновление навигации и меню пользователя
    - Добавление индикаторов (лайки, закладки, подписки)
    - _Requirements: All_

  - [x] 15.2 Настройка маршрутов и безопасности
    - Добавление всех новых маршрутов в routes.yaml (автоматически через атрибуты)
    - Настройка прав доступа в security.yaml (уже настроено)
    - Rate limiting для API endpoints (уже настроено)
    - _Requirements: All_

  - [ ]* 15.3 Integration тесты для полных пользовательских сценариев
    - Тестирование комплексных workflow (создание плейлиста -> добавление видео -> просмотр)
    - Проверка взаимодействия между различными функциями
    - _Requirements: All_

- [x] 16. Финальный checkpoint - Полная система готова
  - Все основные функции реализованы и интегрированы

## Notes

- Задачи помеченные `*` являются опциональными и могут быть пропущены для более быстрого MVP
- Каждая задача ссылается на конкретные требования для отслеживаемости
- Checkpoint'ы обеспечивают инкрементальную валидацию
- Property тесты валидируют универсальные свойства корректности
- Unit тесты валидируют конкретные примеры и граничные случаи