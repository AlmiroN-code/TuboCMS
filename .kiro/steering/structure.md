# Project Structure

## Directory Organization

```
rextube/
├── src/                          # PHP application code
│   ├── Command/                  # CLI commands
│   │   ├── ClearSettingsCacheCommand.php
│   │   └── GenerateVideoPreviewsCommand.php
│   ├── Controller/               # HTTP request handlers
│   │   ├── Admin/                # Admin panel controllers
│   │   │   ├── AdminCategoryController.php
│   │   │   ├── AdminCommentController.php
│   │   │   ├── AdminModelController.php
│   │   │   ├── AdminSettingsController.php
│   │   │   ├── AdminSystemController.php
│   │   │   ├── AdminTagController.php
│   │   │   ├── AdminUserController.php
│   │   │   └── AdminVideoController.php
│   │   ├── CategoryController.php
│   │   ├── CommentController.php
│   │   ├── HomeController.php
│   │   ├── SecurityController.php
│   │   ├── SitemapController.php
│   │   ├── VideoController.php
│   │   └── VideoUploadController.php
│   ├── Entity/                   # Doctrine ORM entities
│   │   ├── Category.php
│   │   ├── Comment.php
│   │   ├── ModelProfile.php
│   │   ├── SiteSetting.php
│   │   ├── Subscription.php
│   │   ├── Tag.php
│   │   ├── User.php
│   │   ├── Video.php
│   │   ├── VideoEncodingProfile.php
│   │   └── VideoFile.php
│   ├── Form/                     # Symfony form types
│   │   ├── RegistrationType.php
│   │   └── VideoUploadType.php
│   ├── Message/                  # Messenger message classes
│   │   └── ProcessVideoMessage.php
│   ├── MessageHandler/           # Messenger message handlers
│   │   └── ProcessVideoMessageHandler.php
│   ├── Repository/               # Doctrine repositories
│   │   ├── CategoryRepository.php
│   │   ├── CommentRepository.php
│   │   ├── ModelProfileRepository.php
│   │   ├── SiteSettingRepository.php
│   │   ├── SubscriptionRepository.php
│   │   ├── TagRepository.php
│   │   ├── UserRepository.php
│   │   ├── VideoEncodingProfileRepository.php
│   │   ├── VideoFileRepository.php
│   │   └── VideoRepository.php
│   ├── Service/                  # Business logic services
│   │   ├── NotificationService.php
│   │   ├── SettingsService.php
│   │   ├── StatsService.php
│   │   └── VideoProcessingService.php
│   ├── Twig/                     # Twig extensions
│   │   └── SettingsExtension.php
│   ├── DataFixtures/             # Test data fixtures
│   │   └── AppFixtures.php
│   └── Kernel.php                # Symfony kernel
├── config/                       # Symfony configuration
│   ├── packages/                 # Bundle-specific config
│   │   ├── cache.yaml
│   │   ├── csrf.yaml
│   │   ├── doctrine.yaml
│   │   ├── doctrine_migrations.yaml
│   │   ├── framework.yaml
│   │   ├── mailer.yaml
│   │   ├── messenger.yaml
│   │   ├── property_info.yaml
│   │   ├── rate_limiter.yaml
│   │   ├── routing.yaml
│   │   ├── security.yaml
│   │   ├── twig.yaml
│   │   ├── ux_turbo.yaml
│   │   ├── validator.yaml
│   │   ├── webpack_encore.yaml
│   │   └── web_profiler.yaml
│   ├── routes/                   # Route definitions
│   ├── bundles.php               # Bundle registration
│   ├── preload.php               # Preload configuration
│   ├── reference.php             # Reference configuration
│   ├── routes.yaml               # Main routes
│   └── services.yaml             # Service container
├── templates/                    # Twig templates
│   ├── admin/                    # Admin panel templates
│   │   ├── base.html.twig
│   │   ├── dashboard.html.twig
│   │   ├── categories/
│   │   ├── comments/
│   │   ├── models/
│   │   ├── settings/
│   │   ├── system/
│   │   ├── tags/
│   │   ├── users/
│   │   └── videos/
│   ├── category/                 # Category pages
│   ├── comment/                  # Comment partials
│   ├── emails/                   # Email templates
│   ├── home/                     # Homepage
│   ├── partials/                 # Reusable components
│   │   ├── _footer.html.twig
│   │   ├── _header.html.twig
│   │   └── _sidebar.html.twig
│   ├── security/                 # Auth pages
│   ├── sitemap/                  # SEO sitemap
│   ├── video/                    # Video pages
│   │   ├── detail.html.twig
│   │   ├── list.html.twig
│   │   ├── my_videos.html.twig
│   │   ├── upload.html.twig
│   │   └── _card.html.twig
│   └── base.html.twig            # Main layout
├── assets/                       # Frontend source files
│   ├── app.js                    # Main JS entry
│   ├── controllers/              # Stimulus controllers
│   │   ├── csrf_protection_controller.js
│   │   └── hello_controller.js
│   ├── styles/                   # CSS/Tailwind
│   │   └── app.css
│   ├── stimulus_bootstrap.js     # Stimulus setup
│   ├── video-preview.js          # Video preview logic
│   └── controllers.json          # Stimulus manifest
├── public/                       # Web root
│   ├── index.php                 # Entry point
│   ├── build/                    # Compiled Webpack assets
│   ├── bundles/                  # Bundle assets
│   └── media/                    # User uploads
│       ├── avatars/
│       ├── posters/
│       ├── previews/
│       ├── site/
│       └── videos/
├── migrations/                   # Database migrations
│   ├── Version20251207172727.php
│   └── Version20251208153545.php
├── var/                          # Runtime files
│   ├── cache/                    # Symfony cache
│   ├── log/                      # Application logs
│   └── share/                    # Shared data
├── composer.json                 # PHP dependencies
├── package.json                  # Node.js dependencies
├── webpack.config.js             # Webpack configuration
├── tailwind.config.js            # Tailwind CSS config
├── postcss.config.js             # PostCSS config
├── .env                          # Environment variables
├── .env.dev                      # Dev environment
├── .editorconfig                 # Editor settings
├── .gitignore                    # Git ignore rules
└── README.md                     # Project documentation
```

## Key Architectural Patterns

### Controllers
- Located in `src/Controller/`
- Admin controllers in `src/Controller/Admin/`
- Handle HTTP requests and return responses
- Use dependency injection for services

### Entities
- Located in `src/Entity/`
- Doctrine ORM mapped classes
- Define database schema through annotations
- Include validation rules

### Services
- Located in `src/Service/`
- Contain business logic
- Registered in `config/services.yaml`
- Injected into controllers and other services

### Repositories
- Located in `src/Repository/`
- Handle database queries
- Extend `ServiceEntityRepository`
- One repository per entity

### Messages & Handlers
- `src/Message/` - Message classes for async jobs
- `src/MessageHandler/` - Handlers that process messages
- Used for video processing via Messenger

### Templates
- Organized by feature/section
- Use Twig templating language
- Partials prefixed with underscore (`_`)
- Admin templates in separate `admin/` folder

### Assets
- `assets/` contains source files
- Compiled to `public/build/` by Webpack Encore
- Stimulus controllers for interactivity
- Tailwind CSS for styling

## Naming Conventions

- **Classes**: PascalCase (e.g., `VideoController`, `ProcessVideoMessage`)
- **Files**: Match class names (e.g., `VideoController.php`)
- **Methods**: camelCase (e.g., `getVideoById()`)
- **Templates**: snake_case with `.html.twig` extension
- **Database tables**: snake_case (auto-generated from entity names)
- **Routes**: kebab-case in URLs (e.g., `/my-videos`)

## Important Directories

- `src/` - All PHP code (controllers, entities, services)
- `templates/` - All Twig templates
- `assets/` - Frontend source (CSS, JS)
- `public/media/` - User-uploaded files
- `config/` - Application configuration
- `migrations/` - Database version control
