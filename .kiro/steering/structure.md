# Project Structure

## Key Directories
```
rextube/
├── assets/              # Frontend исходники
│   ├── controllers/     # Stimulus контроллеры (15 файлов)
│   ├── js/             # JavaScript модули
│   ├── styles/         # CSS (app.css, admin-dark-theme.css)
│   └── icons/          # SVG иконки
├── bin/                # Исполняемые файлы (console)
├── config/             # Конфигурация Symfony
│   ├── packages/       # Конфиги пакетов
│   └── routes/         # Маршруты
├── docs/               # Документация (7 файлов)
├── migrations/         # Миграции БД (45+ файлов)
├── public/             # Публичная директория
│   ├── build/          # Собранные assets (Encore)
│   └── media/          # Загруженные файлы
│       ├── videos/     # Видео файлы
│       ├── posters/    # Постеры
│       ├── previews/   # Превью
│       ├── avatars/    # Аватары
│       ├── covers/     # Обложки
│       └── categories/ # Постеры категорий
├── src/
│   ├── Action/         # Action классы
│   ├── Command/        # Console команды (45+ команд)
│   ├── Controller/     # HTTP контроллеры
│   │   ├── Admin/      # Админ контроллеры (28 файлов)
│   │   └── Api/        # API контроллеры (7 файлов)
│   ├── Entity/         # Doctrine сущности (45+ сущностей)
│   │   └── MaterializedView/ # Materialized views
│   ├── EventListener/  # Event listeners
│   ├── EventSubscriber/# Event subscribers
│   ├── Form/           # Symfony формы
│   │   └── Autocomplete/ # Autocomplete поля
│   ├── Message/        # Messenger сообщения (6 типов)
│   ├── MessageHandler/ # Messenger обработчики
│   ├── Repository/     # Doctrine репозитории (40+ репозиториев)
│   ├── Scheduler/      # Планировщик задач
│   │   ├── Handler/    # Обработчики задач
│   │   └── Message/    # Сообщения планировщика
│   ├── Security/       # Безопасность
│   │   └── Voter/      # Voters для проверки прав
│   ├── Service/        # Бизнес-логика (50+ сервисов)
│   │   ├── CircuitBreaker/ # Circuit breaker pattern
│   │   └── Interface/  # Интерфейсы сервисов
│   ├── Storage/        # Адаптеры хранилищ
│   │   ├── Adapter/    # 6 адаптеров (Local, S3, FTP, SFTP, HTTP, BunnyCDN)
│   │   ├── DTO/        # Data Transfer Objects
│   │   └── Factory/    # Фабрики адаптеров
│   ├── Twig/           # Twig расширения (20+ расширений)
│   │   └── Components/ # Live Components
│   └── Validator/      # Валидаторы
├── templates/          # Twig шаблоны
│   ├── admin/          # Админ-панель (23 подпапки)
│   ├── ads/            # Реклама
│   ├── channel/        # Каналы
│   ├── live/           # Live стримы
│   ├── video/          # Видео
│   ├── partials/       # Переиспользуемые части
│   └── emails/         # Email шаблоны
├── tests/              # Тесты (83 теста, 309 assertions)
├── translations/       # Переводы (i18n)
└── var/                # Временные файлы (cache, logs)
```

## Naming Conventions
- **Classes**: PascalCase (`VideoController`, `UserService`)
- **Methods**: camelCase (`processVideo()`, `getUserById()`)
- **Templates**: snake_case.html.twig (`video_detail.html.twig`)
- **Routes**: kebab-case URLs (`/admin/video-settings`)
- **DB tables**: snake_case (auto-generated: `video_file`, `channel_subscription`)
- **Stimulus controllers**: kebab-case (`video-player_controller.js`)

## Architecture Patterns

### Controllers
- **Admin controllers** - `/admin/*` маршруты, CRUD операции
- **API controllers** - `/api/*` маршруты, JSON responses
- **Frontend controllers** - публичные страницы
- Dependency injection через конструктор
- Атрибуты для маршрутов: `#[Route('/path', name: 'route_name')]`

### Entities (45+ сущностей)
**Основные:**
- User, Video, VideoFile, VideoChapter, VideoEncodingProfile
- Channel, ChannelPlaylist, ChannelAnalytics, ChannelDonation
- Category, Tag, Comment, ChatMessage
- LiveStream, ModelProfile, Post, Series, Season
- Ad, AdCampaign, AdPlacement, AdSegment, AdStatistic, AdAbTest
- Role, Permission, Notification, PushSubscription
- Storage, ContentProtectionSetting, SiteSetting

**Связи:**
- Doctrine attributes (не annotations)
- Bidirectional relations с cascade
- Indexes для оптимизации

### Services (50+ сервисов)
**Категории:**
- **Video**: VideoProcessingService, SignedUrlService, ContentProtectionService
- **Channel**: ChannelService, ChannelAnalyticsService
- **User**: UserStatsService, RolePermissionService
- **Storage**: StorageManager, StorageStatsService, MigrationReportService
- **Analytics**: StatsService, ModelStatsService, PerformanceMonitorService
- **Ads**: AdService, ImpressionTracker
- **Notifications**: NotificationService, PushNotificationService, AdminNotifierService
- **Search**: SearchService, RecommendationService
- **Cache**: CacheService, CacheOptimizationService, MaterializedViewService
- **System**: MessengerWorkerService, SystemMonitoringService

### Repositories
- Custom query methods для сложных выборок
- QueryBuilder для динамических запросов
- Оптимизация через indexes и joins

### Templates Organization
```
templates/
├── base.html.twig           # Базовый layout
├── admin/
│   ├── base.html.twig       # Admin layout
│   ├── dashboard.html.twig  # Главная админки
│   └── [feature]/           # По функциям
├── partials/
│   ├── _header.html.twig    # Шапка
│   ├── _footer.html.twig    # Подвал
│   ├── _sidebar.html.twig   # Сайдбар
│   └── _pagination.html.twig # Пагинация
└── [feature]/
    ├── index.html.twig      # Список
    ├── show.html.twig       # Детальная
    ├── create.html.twig     # Создание
    └── _card.html.twig      # Partial (с _)
```

## Laragon Environment
- **nginx**: 1.29.4
- **PHP**: 8.4.16 (NTS, VS17, x64)
- **NodeJS**: v22
- **MySQL**: 8.0
- **URL**: http://tubocms.test:8080/
- **Root**: /public

## File Organization Rules
- **Partials** начинаются с `_` (underscore)
- **Admin templates** в `templates/admin/`
- **API controllers** возвращают JSON
- **Services** инжектятся через конструктор
- **Repositories** только для запросов к БД
- **EventSubscribers** для cross-cutting concerns