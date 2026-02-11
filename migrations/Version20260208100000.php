<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Инициализация базовых разрешений и ролей системы
 */
final class Version20260208100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Инициализация базовых разрешений и ролей системы';
    }

    public function up(Schema $schema): void
    {
        // Вставка разрешений
        $permissions = [
            // Видео
            ['video.view', 'Просмотр видео', 'video', 'Просмотр списка и деталей видео'],
            ['video.create', 'Создание видео', 'video', 'Загрузка новых видео'],
            ['video.edit', 'Редактирование видео', 'video', 'Изменение информации о видео'],
            ['video.delete', 'Удаление видео', 'video', 'Удаление видео из системы'],
            ['video.moderate', 'Модерация видео', 'video', 'Публикация/снятие с публикации видео'],
            ['video.reprocess', 'Переобработка видео', 'video', 'Запуск повторной обработки видео'],
            
            // Категории
            ['category.view', 'Просмотр категорий', 'category', 'Просмотр списка категорий'],
            ['category.create', 'Создание категорий', 'category', 'Добавление новых категорий'],
            ['category.edit', 'Редактирование категорий', 'category', 'Изменение категорий'],
            ['category.delete', 'Удаление категорий', 'category', 'Удаление категорий'],
            
            // Теги
            ['tag.view', 'Просмотр тегов', 'tag', 'Просмотр списка тегов'],
            ['tag.create', 'Создание тегов', 'tag', 'Добавление новых тегов'],
            ['tag.edit', 'Редактирование тегов', 'tag', 'Изменение тегов'],
            ['tag.delete', 'Удаление тегов', 'tag', 'Удаление тегов'],
            
            // Модели
            ['model.view', 'Просмотр моделей', 'model', 'Просмотр списка моделей'],
            ['model.create', 'Создание моделей', 'model', 'Добавление новых моделей'],
            ['model.edit', 'Редактирование моделей', 'model', 'Изменение информации о моделях'],
            ['model.delete', 'Удаление моделей', 'model', 'Удаление моделей'],
            ['model.verify', 'Верификация моделей', 'model', 'Верификация/снятие верификации моделей'],
            
            // Каналы
            ['channel.view', 'Просмотр каналов', 'channel', 'Просмотр списка каналов'],
            ['channel.create', 'Создание каналов', 'channel', 'Создание новых каналов'],
            ['channel.edit', 'Редактирование каналов', 'channel', 'Изменение информации о каналах'],
            ['channel.delete', 'Удаление каналов', 'channel', 'Удаление каналов'],
            ['channel.verify', 'Верификация каналов', 'channel', 'Верификация/снятие верификации каналов'],
            
            // Пользователи
            ['user.view', 'Просмотр пользователей', 'user', 'Просмотр списка пользователей'],
            ['user.create', 'Создание пользователей', 'user', 'Создание новых пользователей'],
            ['user.edit', 'Редактирование пользователей', 'user', 'Изменение данных пользователей'],
            ['user.delete', 'Удаление пользователей', 'user', 'Удаление пользователей'],
            ['user.verify', 'Верификация пользователей', 'user', 'Верификация пользователей'],
            ['user.premium', 'Управление премиум', 'user', 'Выдача/снятие премиум статуса'],
            
            // Комментарии
            ['comment.view', 'Просмотр комментариев', 'comment', 'Просмотр списка комментариев'],
            ['comment.moderate', 'Модерация комментариев', 'comment', 'Одобрение/отклонение комментариев'],
            ['comment.delete', 'Удаление комментариев', 'comment', 'Удаление комментариев'],
            
            // Плейлисты
            ['playlist.view', 'Просмотр плейлистов', 'playlist', 'Просмотр списка плейлистов'],
            ['playlist.edit', 'Редактирование плейлистов', 'playlist', 'Изменение плейлистов'],
            ['playlist.delete', 'Удаление плейлистов', 'playlist', 'Удаление плейлистов'],
            
            // Посты
            ['post.view', 'Просмотр постов', 'post', 'Просмотр списка постов'],
            ['post.create', 'Создание постов', 'post', 'Создание новых постов'],
            ['post.edit', 'Редактирование постов', 'post', 'Изменение постов'],
            ['post.delete', 'Удаление постов', 'post', 'Удаление постов'],
            
            // Реклама
            ['ad.view', 'Просмотр рекламы', 'advertising', 'Просмотр рекламных объявлений'],
            ['ad.create', 'Создание рекламы', 'advertising', 'Создание рекламных объявлений'],
            ['ad.edit', 'Редактирование рекламы', 'advertising', 'Изменение рекламных объявлений'],
            ['ad.delete', 'Удаление рекламы', 'advertising', 'Удаление рекламных объявлений'],
            ['ad.stats', 'Статистика рекламы', 'advertising', 'Просмотр статистики рекламы'],
            
            // Роли и разрешения
            ['role.view', 'Просмотр ролей', 'security', 'Просмотр списка ролей'],
            ['role.create', 'Создание ролей', 'security', 'Создание новых ролей'],
            ['role.edit', 'Редактирование ролей', 'security', 'Изменение ролей'],
            ['role.delete', 'Удаление ролей', 'security', 'Удаление ролей'],
            ['permission.view', 'Просмотр разрешений', 'security', 'Просмотр списка разрешений'],
            ['permission.create', 'Создание разрешений', 'security', 'Создание новых разрешений'],
            ['permission.edit', 'Редактирование разрешений', 'security', 'Изменение разрешений'],
            ['permission.delete', 'Удаление разрешений', 'security', 'Удаление разрешений'],
            
            // Настройки
            ['settings.view', 'Просмотр настроек', 'settings', 'Просмотр настроек системы'],
            ['settings.edit', 'Изменение настроек', 'settings', 'Изменение настроек системы'],
            ['storage.manage', 'Управление хранилищами', 'settings', 'Управление хранилищами файлов'],
            ['transcoding.manage', 'Управление транскодингом', 'settings', 'Настройка параметров транскодинга'],
            
            // Система
            ['cache.manage', 'Управление кэшем', 'system', 'Очистка и управление кэшем'],
            ['worker.manage', 'Управление воркерами', 'system', 'Управление фоновыми задачами'],
            ['workflow.manage', 'Управление workflow', 'system', 'Управление рабочими процессами'],
            ['notification.manage', 'Управление уведомлениями', 'system', 'Управление системой уведомлений'],
            
            // Live Streaming
            ['stream.view', 'Просмотр стримов', 'streaming', 'Просмотр списка стримов'],
            ['stream.create', 'Создание стримов', 'streaming', 'Создание новых стримов'],
            ['stream.manage', 'Управление стримами', 'streaming', 'Управление стримами пользователей'],
            ['stream.delete', 'Удаление стримов', 'streaming', 'Удаление стримов'],
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($permissions as $perm) {
            $this->addSql(
                "INSERT INTO permission (name, display_name, category, description, is_active, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, 1, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 display_name = VALUES(display_name), 
                 category = VALUES(category), 
                 description = VALUES(description),
                 updated_at = VALUES(updated_at)",
                [$perm[0], $perm[1], $perm[2], $perm[3], $now, $now]
            );
        }

        // Вставка ролей
        $roles = [
            ['super_admin', 'Супер Администратор', 'Полный доступ ко всем функциям системы'],
            ['admin', 'Администратор', 'Администрирование контента и пользователей'],
            ['moderator', 'Модератор', 'Модерация контента и комментариев'],
            ['content_manager', 'Контент-менеджер', 'Управление контентом и категориями'],
            ['creator', 'Создатель контента', 'Расширенные возможности для создателей'],
            ['premium', 'Премиум пользователь', 'Премиум функции и возможности'],
            ['user', 'Пользователь', 'Обычный зарегистрированный пользователь'],
        ];

        foreach ($roles as $role) {
            $this->addSql(
                "INSERT INTO role (name, display_name, description, is_active, created_at, updated_at) 
                 VALUES (?, ?, ?, 1, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 display_name = VALUES(display_name), 
                 description = VALUES(description),
                 updated_at = VALUES(updated_at)",
                [$role[0], $role[1], $role[2], $now, $now]
            );
        }

        // Назначение разрешений ролям
        $rolePermissions = [
            'super_admin' => 'all', // Все разрешения
            'admin' => [
                'video.view', 'video.create', 'video.edit', 'video.delete', 'video.moderate', 'video.reprocess',
                'category.view', 'category.create', 'category.edit', 'category.delete',
                'tag.view', 'tag.create', 'tag.edit', 'tag.delete',
                'model.view', 'model.create', 'model.edit', 'model.delete', 'model.verify',
                'channel.view', 'channel.create', 'channel.edit', 'channel.delete', 'channel.verify',
                'user.view', 'user.create', 'user.edit', 'user.delete', 'user.verify', 'user.premium',
                'comment.view', 'comment.moderate', 'comment.delete',
                'playlist.view', 'playlist.edit', 'playlist.delete',
                'post.view', 'post.create', 'post.edit', 'post.delete',
                'ad.view', 'ad.create', 'ad.edit', 'ad.delete', 'ad.stats',
                'settings.view', 'settings.edit',
                'stream.view', 'stream.manage', 'stream.delete'
            ],
            'moderator' => [
                'video.view', 'video.moderate', 'video.delete',
                'category.view', 'tag.view',
                'model.view', 'model.verify',
                'channel.view', 'channel.verify',
                'user.view', 'user.verify',
                'comment.view', 'comment.moderate', 'comment.delete',
                'playlist.view', 'post.view',
                'stream.view', 'stream.manage'
            ],
            'content_manager' => [
                'video.view', 'video.create', 'video.edit', 'video.moderate',
                'category.view', 'category.create', 'category.edit',
                'tag.view', 'tag.create', 'tag.edit',
                'model.view', 'model.create', 'model.edit',
                'channel.view',
                'playlist.view', 'playlist.edit',
                'post.view', 'post.create', 'post.edit'
            ],
            'creator' => [
                'video.view', 'video.create', 'video.edit',
                'category.view', 'tag.view', 'tag.create',
                'model.view',
                'channel.view', 'channel.create', 'channel.edit',
                'comment.view',
                'playlist.view',
                'post.view', 'post.create', 'post.edit',
                'stream.view', 'stream.create'
            ],
            'premium' => [
                'video.view', 'video.create',
                'category.view', 'tag.view',
                'model.view', 'channel.view',
                'comment.view',
                'playlist.view',
                'stream.view', 'stream.create'
            ],
            'user' => [
                'video.view', 'video.create',
                'category.view', 'tag.view',
                'model.view', 'channel.view',
                'comment.view',
                'playlist.view',
                'stream.view'
            ]
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            if ($permissions === 'all') {
                // Супер админ получает все разрешения
                $this->addSql(
                    "INSERT INTO role_permission (role_id, permission_id)
                     SELECT r.id, p.id 
                     FROM role r, permission p 
                     WHERE r.name = ? AND p.is_active = 1
                     ON DUPLICATE KEY UPDATE role_id = role_id",
                    [$roleName]
                );
            } else {
                // Назначаем указанные разрешения
                foreach ($permissions as $permName) {
                    $this->addSql(
                        "INSERT INTO role_permission (role_id, permission_id)
                         SELECT r.id, p.id 
                         FROM role r, permission p 
                         WHERE r.name = ? AND p.name = ? AND p.is_active = 1
                         ON DUPLICATE KEY UPDATE role_id = role_id",
                        [$roleName, $permName]
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Удаление связей роль-разрешение
        $this->addSql('DELETE FROM role_permission');
        
        // Удаление ролей
        $this->addSql("DELETE FROM role WHERE name IN ('super_admin', 'admin', 'moderator', 'content_manager', 'creator', 'premium', 'user')");
        
        // Удаление разрешений
        $this->addSql('DELETE FROM permission');
    }
}
