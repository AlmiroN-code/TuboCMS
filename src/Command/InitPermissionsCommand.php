<?php

namespace App\Command;

use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-permissions',
    description: 'Инициализация базовых прав доступа и ролей',
)]
class InitPermissionsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Определяем базовые права доступа
        $permissions = [
            // Управление видео
            ['name' => 'video.view', 'displayName' => 'Просмотр видео', 'description' => 'Просмотр списка и деталей видео', 'category' => 'video'],
            ['name' => 'video.create', 'displayName' => 'Создание видео', 'description' => 'Загрузка новых видео', 'category' => 'video'],
            ['name' => 'video.edit', 'displayName' => 'Редактирование видео', 'description' => 'Редактирование своих видео', 'category' => 'video'],
            ['name' => 'video.edit_all', 'displayName' => 'Редактирование всех видео', 'description' => 'Редактирование любых видео', 'category' => 'video'],
            ['name' => 'video.delete', 'displayName' => 'Удаление видео', 'description' => 'Удаление своих видео', 'category' => 'video'],
            ['name' => 'video.delete_all', 'displayName' => 'Удаление всех видео', 'description' => 'Удаление любых видео', 'category' => 'video'],
            ['name' => 'video.moderate', 'displayName' => 'Модерация видео', 'description' => 'Одобрение и отклонение видео', 'category' => 'video'],

            // Управление пользователями
            ['name' => 'user.view', 'displayName' => 'Просмотр пользователей', 'description' => 'Просмотр списка пользователей', 'category' => 'user'],
            ['name' => 'user.create', 'displayName' => 'Создание пользователей', 'description' => 'Создание новых пользователей', 'category' => 'user'],
            ['name' => 'user.edit', 'displayName' => 'Редактирование пользователей', 'description' => 'Редактирование данных пользователей', 'category' => 'user'],
            ['name' => 'user.delete', 'displayName' => 'Удаление пользователей', 'description' => 'Удаление пользователей', 'category' => 'user'],
            ['name' => 'user.ban', 'displayName' => 'Блокировка пользователей', 'description' => 'Блокировка и разблокировка пользователей', 'category' => 'user'],

            // Управление комментариями
            ['name' => 'comment.view', 'displayName' => 'Просмотр комментариев', 'description' => 'Просмотр комментариев', 'category' => 'comment'],
            ['name' => 'comment.create', 'displayName' => 'Создание комментариев', 'description' => 'Написание комментариев', 'category' => 'comment'],
            ['name' => 'comment.edit', 'displayName' => 'Редактирование комментариев', 'description' => 'Редактирование своих комментариев', 'category' => 'comment'],
            ['name' => 'comment.edit_all', 'displayName' => 'Редактирование всех комментариев', 'description' => 'Редактирование любых комментариев', 'category' => 'comment'],
            ['name' => 'comment.delete', 'displayName' => 'Удаление комментариев', 'description' => 'Удаление своих комментариев', 'category' => 'comment'],
            ['name' => 'comment.delete_all', 'displayName' => 'Удаление всех комментариев', 'description' => 'Удаление любых комментариев', 'category' => 'comment'],

            // Управление категориями
            ['name' => 'category.view', 'displayName' => 'Просмотр категорий', 'description' => 'Просмотр списка категорий', 'category' => 'category'],
            ['name' => 'category.create', 'displayName' => 'Создание категорий', 'description' => 'Создание новых категорий', 'category' => 'category'],
            ['name' => 'category.edit', 'displayName' => 'Редактирование категорий', 'description' => 'Редактирование категорий', 'category' => 'category'],
            ['name' => 'category.delete', 'displayName' => 'Удаление категорий', 'description' => 'Удаление категорий', 'category' => 'category'],

            // Управление тегами
            ['name' => 'tag.view', 'displayName' => 'Просмотр тегов', 'description' => 'Просмотр списка тегов', 'category' => 'tag'],
            ['name' => 'tag.create', 'displayName' => 'Создание тегов', 'description' => 'Создание новых тегов', 'category' => 'tag'],
            ['name' => 'tag.edit', 'displayName' => 'Редактирование тегов', 'description' => 'Редактирование тегов', 'category' => 'tag'],
            ['name' => 'tag.delete', 'displayName' => 'Удаление тегов', 'description' => 'Удаление тегов', 'category' => 'tag'],

            // Управление моделями
            ['name' => 'model.view', 'displayName' => 'Просмотр моделей', 'description' => 'Просмотр списка моделей', 'category' => 'model'],
            ['name' => 'model.create', 'displayName' => 'Создание моделей', 'description' => 'Создание профилей моделей', 'category' => 'model'],
            ['name' => 'model.edit', 'displayName' => 'Редактирование моделей', 'description' => 'Редактирование профилей моделей', 'category' => 'model'],
            ['name' => 'model.delete', 'displayName' => 'Удаление моделей', 'description' => 'Удаление профилей моделей', 'category' => 'model'],

            // Управление настройками
            ['name' => 'settings.view', 'displayName' => 'Просмотр настроек', 'description' => 'Просмотр настроек сайта', 'category' => 'settings'],
            ['name' => 'settings.edit', 'displayName' => 'Редактирование настроек', 'description' => 'Изменение настроек сайта', 'category' => 'settings'],

            // Управление ролями и правами
            ['name' => 'role.view', 'displayName' => 'Просмотр ролей', 'description' => 'Просмотр списка ролей', 'category' => 'role'],
            ['name' => 'role.create', 'displayName' => 'Создание ролей', 'description' => 'Создание новых ролей', 'category' => 'role'],
            ['name' => 'role.edit', 'displayName' => 'Редактирование ролей', 'description' => 'Редактирование ролей', 'category' => 'role'],
            ['name' => 'role.delete', 'displayName' => 'Удаление ролей', 'description' => 'Удаление ролей', 'category' => 'role'],

            // Доступ к админ-панели
            ['name' => 'admin.access', 'displayName' => 'Доступ к админ-панели', 'description' => 'Доступ к административной панели', 'category' => 'admin'],
            ['name' => 'admin.dashboard', 'displayName' => 'Просмотр дашборда', 'description' => 'Просмотр административного дашборда', 'category' => 'admin'],
            ['name' => 'admin.system', 'displayName' => 'Системные настройки', 'description' => 'Доступ к системным настройкам', 'category' => 'admin'],
        ];

        $io->section('Создание прав доступа');
        $permissionEntities = [];
        
        foreach ($permissions as $permData) {
            $permission = $this->entityManager->getRepository(Permission::class)
                ->findOneBy(['name' => $permData['name']]);

            if (!$permission) {
                $permission = new Permission();
                $permission->setName($permData['name']);
                $permission->setDisplayName($permData['displayName']);
                $permission->setDescription($permData['description']);
                $permission->setCategory($permData['category']);
                $permission->setActive(true);

                $this->entityManager->persist($permission);
                $io->writeln(sprintf('✓ Создано право: %s', $permData['displayName']));
            } else {
                $io->writeln(sprintf('- Право уже существует: %s', $permData['displayName']));
            }

            $permissionEntities[$permData['name']] = $permission;
        }

        $this->entityManager->flush();

        // Определяем базовые роли
        $roles = [
            [
                'name' => 'ROLE_USER',
                'displayName' => 'Пользователь',
                'description' => 'Обычный пользователь сайта',
                'permissions' => [
                    'video.view', 'video.create', 'video.edit', 'video.delete',
                    'comment.view', 'comment.create', 'comment.edit', 'comment.delete',
                    'category.view', 'tag.view', 'model.view',
                ]
            ],
            [
                'name' => 'ROLE_MODERATOR',
                'displayName' => 'Модератор',
                'description' => 'Модератор контента',
                'permissions' => [
                    'video.view', 'video.create', 'video.edit', 'video.edit_all', 'video.delete', 'video.delete_all', 'video.moderate',
                    'comment.view', 'comment.create', 'comment.edit', 'comment.edit_all', 'comment.delete', 'comment.delete_all',
                    'category.view', 'tag.view', 'model.view',
                    'user.view', 'user.ban',
                    'admin.access', 'admin.dashboard',
                ]
            ],
            [
                'name' => 'ROLE_ADMIN',
                'displayName' => 'Администратор',
                'description' => 'Полный доступ к управлению сайтом',
                'permissions' => array_keys($permissionEntities),
            ],
        ];

        $io->section('Создание ролей');

        foreach ($roles as $roleData) {
            $role = $this->entityManager->getRepository(Role::class)
                ->findOneBy(['name' => $roleData['name']]);

            if (!$role) {
                $role = new Role();
                $role->setName($roleData['name']);
                $role->setDisplayName($roleData['displayName']);
                $role->setDescription($roleData['description']);
                $role->setActive(true);

                $this->entityManager->persist($role);
                $io->writeln(sprintf('✓ Создана роль: %s', $roleData['displayName']));
            } else {
                $io->writeln(sprintf('- Роль уже существует: %s', $roleData['displayName']));
                // Очищаем существующие права для обновления
                foreach ($role->getPermissions() as $perm) {
                    $role->removePermission($perm);
                }
            }

            // Добавляем права к роли
            foreach ($roleData['permissions'] as $permName) {
                if (isset($permissionEntities[$permName])) {
                    $role->addPermission($permissionEntities[$permName]);
                }
            }
        }

        $this->entityManager->flush();

        $io->success('Права доступа и роли успешно инициализированы!');
        $io->note(sprintf('Создано прав: %d', count($permissions)));
        $io->note(sprintf('Создано ролей: %d', count($roles)));

        return Command::SUCCESS;
    }
}
