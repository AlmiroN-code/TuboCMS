<?php

namespace App\Command;

use App\Entity\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-permissions',
    description: 'Инициализация базовых разрешений системы',
)]
class InitPermissionsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $permissions = [
            // Видео
            ['name' => 'video.view', 'displayName' => 'Просмотр видео', 'category' => 'video', 'description' => 'Просмотр списка и деталей видео'],
            ['name' => 'video.create', 'displayName' => 'Создание видео', 'category' => 'video', 'description' => 'Загрузка новых видео'],
            ['name' => 'video.edit', 'displayName' => 'Редактирование видео', 'category' => 'video', 'description' => 'Изменение информации о видео'],
            ['name' => 'video.delete', 'displayName' => 'Удаление видео', 'category' => 'video', 'description' => 'Удаление видео из системы'],
            ['name' => 'video.moderate', 'displayName' => 'Модерация видео', 'category' => 'video', 'description' => 'Публикация/снятие с публикации видео'],
            ['name' => 'video.reprocess', 'displayName' => 'Переобработка видео', 'category' => 'video', 'description' => 'Запуск повторной обработки видео'],
            
            // Категории
            ['name' => 'category.view', 'displayName' => 'Просмотр категорий', 'category' => 'category', 'description' => 'Просмотр списка категорий'],
            ['name' => 'category.create', 'displayName' => 'Создание категорий', 'category' => 'category', 'description' => 'Добавление новых категорий'],
            ['name' => 'category.edit', 'displayName' => 'Редактирование категорий', 'category' => 'category', 'description' => 'Изменение категорий'],
            ['name' => 'category.delete', 'displayName' => 'Удаление категорий', 'category' => 'category', 'description' => 'Удаление категорий'],
            
            // Теги
            ['name' => 'tag.view', 'displayName' => 'Просмотр тегов', 'category' => 'tag', 'description' => 'Просмотр списка тегов'],
            ['name' => 'tag.create', 'displayName' => 'Создание тегов', 'category' => 'tag', 'description' => 'Добавление новых тегов'],
            ['name' => 'tag.edit', 'displayName' => 'Редактирование тегов', 'category' => 'tag', 'description' => 'Изменение тегов'],
            ['name' => 'tag.delete', 'displayName' => 'Удаление тегов', 'category' => 'tag', 'description' => 'Удаление тегов'],
            
            // Модели
            ['name' => 'model.view', 'displayName' => 'Просмотр моделей', 'category' => 'model', 'description' => 'Просмотр списка моделей'],
            ['name' => 'model.create', 'displayName' => 'Создание моделей', 'category' => 'model', 'description' => 'Добавление новых моделей'],
            ['name' => 'model.edit', 'displayName' => 'Редактирование моделей', 'category' => 'model', 'description' => 'Изменение информации о моделях'],
            ['name' => 'model.delete', 'displayName' => 'Удаление моделей', 'category' => 'model', 'description' => 'Удаление моделей'],
            ['name' => 'model.verify', 'displayName' => 'Верификация моделей', 'category' => 'model', 'description' => 'Верификация/снятие верификации моделей'],
            
            // Каналы
            ['name' => 'channel.view', 'displayName' => 'Просмотр каналов', 'category' => 'channel', 'description' => 'Просмотр списка каналов'],
            ['name' => 'channel.create', 'displayName' => 'Создание каналов', 'category' => 'channel', 'description' => 'Создание новых каналов'],
            ['name' => 'channel.edit', 'displayName' => 'Редактирование каналов', 'category' => 'channel', 'description' => 'Изменение информации о каналах'],
            ['name' => 'channel.delete', 'displayName' => 'Удаление каналов', 'category' => 'channel', 'description' => 'Удаление каналов'],
            ['name' => 'channel.verify', 'displayName' => 'Верификация каналов', 'category' => 'channel', 'description' => 'Верификация/снятие верификации каналов'],
            
            // Пользователи
            ['name' => 'user.view', 'displayName' => 'Просмотр пользователей', 'category' => 'user', 'description' => 'Просмотр списка пользователей'],
            ['name' => 'user.create', 'displayName' => 'Создание пользователей', 'category' => 'user', 'description' => 'Создание новых пользователей'],
            ['name' => 'user.edit', 'displayName' => 'Редактирование пользователей', 'category' => 'user', 'description' => 'Изменение данных пользователей'],
            ['name' => 'user.delete', 'displayName' => 'Удаление пользователей', 'category' => 'user', 'description' => 'Удаление пользователей'],
            ['name' => 'user.verify', 'displayName' => 'Верификация пользователей', 'category' => 'user', 'description' => 'Верификация пользователей'],
            ['name' => 'user.premium', 'displayName' => 'Управление премиум', 'category' => 'user', 'description' => 'Выдача/снятие премиум статуса'],
            
            // Комментарии
            ['name' => 'comment.view', 'displayName' => 'Просмотр комментариев', 'category' => 'comment', 'description' => 'Просмотр списка комментариев'],
            ['name' => 'comment.moderate', 'displayName' => 'Модерация комментариев', 'category' => 'comment', 'description' => 'Одобрение/отклонение комментариев'],
            ['name' => 'comment.delete', 'displayName' => 'Удаление комментариев', 'category' => 'comment', 'description' => 'Удаление комментариев'],
            
            // Плейлисты
            ['name' => 'playlist.view', 'displayName' => 'Просмотр плейлистов', 'category' => 'playlist', 'description' => 'Просмотр списка плейлистов'],
            ['name' => 'playlist.edit', 'displayName' => 'Редактирование плейлистов', 'category' => 'playlist', 'description' => 'Изменение плейлистов'],
            ['name' => 'playlist.delete', 'displayName' => 'Удаление плейлистов', 'category' => 'playlist', 'description' => 'Удаление плейлистов'],
            
            // Посты
            ['name' => 'post.view', 'displayName' => 'Просмотр постов', 'category' => 'post', 'description' => 'Просмотр списка постов'],
            ['name' => 'post.create', 'displayName' => 'Создание постов', 'category' => 'post', 'description' => 'Создание новых постов'],
            ['name' => 'post.edit', 'displayName' => 'Редактирование постов', 'category' => 'post', 'description' => 'Изменение постов'],
            ['name' => 'post.delete', 'displayName' => 'Удаление постов', 'category' => 'post', 'description' => 'Удаление постов'],
            
            // Реклама
            ['name' => 'ad.view', 'displayName' => 'Просмотр рекламы', 'category' => 'advertising', 'description' => 'Просмотр рекламных объявлений'],
            ['name' => 'ad.create', 'displayName' => 'Создание рекламы', 'category' => 'advertising', 'description' => 'Создание рекламных объявлений'],
            ['name' => 'ad.edit', 'displayName' => 'Редактирование рекламы', 'category' => 'advertising', 'description' => 'Изменение рекламных объявлений'],
            ['name' => 'ad.delete', 'displayName' => 'Удаление рекламы', 'category' => 'advertising', 'description' => 'Удаление рекламных объявлений'],
            ['name' => 'ad.stats', 'displayName' => 'Статистика рекламы', 'category' => 'advertising', 'description' => 'Просмотр статистики рекламы'],
            
            // Роли и разрешения
            ['name' => 'role.view', 'displayName' => 'Просмотр ролей', 'category' => 'security', 'description' => 'Просмотр списка ролей'],
            ['name' => 'role.create', 'displayName' => 'Создание ролей', 'category' => 'security', 'description' => 'Создание новых ролей'],
            ['name' => 'role.edit', 'displayName' => 'Редактирование ролей', 'category' => 'security', 'description' => 'Изменение ролей'],
            ['name' => 'role.delete', 'displayName' => 'Удаление ролей', 'category' => 'security', 'description' => 'Удаление ролей'],
            ['name' => 'permission.view', 'displayName' => 'Просмотр разрешений', 'category' => 'security', 'description' => 'Просмотр списка разрешений'],
            ['name' => 'permission.create', 'displayName' => 'Создание разрешений', 'category' => 'security', 'description' => 'Создание новых разрешений'],
            ['name' => 'permission.edit', 'displayName' => 'Редактирование разрешений', 'category' => 'security', 'description' => 'Изменение разрешений'],
            ['name' => 'permission.delete', 'displayName' => 'Удаление разрешений', 'category' => 'security', 'description' => 'Удаление разрешений'],
            
            // Настройки
            ['name' => 'settings.view', 'displayName' => 'Просмотр настроек', 'category' => 'settings', 'description' => 'Просмотр настроек системы'],
            ['name' => 'settings.edit', 'displayName' => 'Изменение настроек', 'category' => 'settings', 'description' => 'Изменение настроек системы'],
            ['name' => 'storage.manage', 'displayName' => 'Управление хранилищами', 'category' => 'settings', 'description' => 'Управление хранилищами файлов'],
            ['name' => 'transcoding.manage', 'displayName' => 'Управление транскодингом', 'category' => 'settings', 'description' => 'Настройка параметров транскодинга'],
            
            // Система
            ['name' => 'cache.manage', 'displayName' => 'Управление кэшем', 'category' => 'system', 'description' => 'Очистка и управление кэшем'],
            ['name' => 'worker.manage', 'displayName' => 'Управление воркерами', 'category' => 'system', 'description' => 'Управление фоновыми задачами'],
            ['name' => 'workflow.manage', 'displayName' => 'Управление workflow', 'category' => 'system', 'description' => 'Управление рабочими процессами'],
            ['name' => 'notification.manage', 'displayName' => 'Управление уведомлениями', 'category' => 'system', 'description' => 'Управление системой уведомлений'],
            
            // Live Streaming
            ['name' => 'stream.view', 'displayName' => 'Просмотр стримов', 'category' => 'streaming', 'description' => 'Просмотр списка стримов'],
            ['name' => 'stream.create', 'displayName' => 'Создание стримов', 'category' => 'streaming', 'description' => 'Создание новых стримов'],
            ['name' => 'stream.manage', 'displayName' => 'Управление стримами', 'category' => 'streaming', 'description' => 'Управление стримами пользователей'],
            ['name' => 'stream.delete', 'displayName' => 'Удаление стримов', 'category' => 'streaming', 'description' => 'Удаление стримов'],
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($permissions as $permData) {
            $permission = $this->em->getRepository(Permission::class)->findOneBy(['name' => $permData['name']]);
            
            if ($permission) {
                // Обновляем существующее разрешение
                $permission->setDisplayName($permData['displayName']);
                $permission->setCategory($permData['category']);
                $permission->setDescription($permData['description']);
                $permission->setUpdatedAt(new \DateTimeImmutable());
                $updated++;
            } else {
                // Создаем новое разрешение
                $permission = new Permission();
                $permission->setName($permData['name']);
                $permission->setDisplayName($permData['displayName']);
                $permission->setCategory($permData['category']);
                $permission->setDescription($permData['description']);
                $permission->setActive(true);
                $created++;
            }
            
            $this->em->persist($permission);
        }

        $this->em->flush();

        $io->success(sprintf(
            'Разрешения инициализированы: создано %d, обновлено %d',
            $created,
            $updated
        ));

        return Command::SUCCESS;
    }
}
