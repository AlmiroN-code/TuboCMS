<?php

namespace App\Command;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-roles',
    description: 'Инициализация базовых ролей системы с разрешениями',
)]
class InitRolesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $roles = [
            [
                'name' => 'super_admin',
                'displayName' => 'Супер Администратор',
                'description' => 'Полный доступ ко всем функциям системы',
                'permissions' => 'all'
            ],
            [
                'name' => 'admin',
                'displayName' => 'Администратор',
                'description' => 'Администрирование контента и пользователей',
                'permissions' => [
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
                ]
            ],
            [
                'name' => 'moderator',
                'displayName' => 'Модератор',
                'description' => 'Модерация контента и комментариев',
                'permissions' => [
                    'video.view', 'video.moderate', 'video.delete',
                    'category.view',
                    'tag.view',
                    'model.view', 'model.verify',
                    'channel.view', 'channel.verify',
                    'user.view', 'user.verify',
                    'comment.view', 'comment.moderate', 'comment.delete',
                    'playlist.view',
                    'post.view',
                    'stream.view', 'stream.manage'
                ]
            ],
            [
                'name' => 'content_manager',
                'displayName' => 'Контент-менеджер',
                'description' => 'Управление контентом и категориями',
                'permissions' => [
                    'video.view', 'video.create', 'video.edit', 'video.moderate',
                    'category.view', 'category.create', 'category.edit',
                    'tag.view', 'tag.create', 'tag.edit',
                    'model.view', 'model.create', 'model.edit',
                    'channel.view',
                    'playlist.view', 'playlist.edit',
                    'post.view', 'post.create', 'post.edit'
                ]
            ],
            [
                'name' => 'creator',
                'displayName' => 'Создатель контента',
                'description' => 'Расширенные возможности для создателей',
                'permissions' => [
                    'video.view', 'video.create', 'video.edit',
                    'category.view',
                    'tag.view', 'tag.create',
                    'model.view',
                    'channel.view', 'channel.create', 'channel.edit',
                    'comment.view',
                    'playlist.view',
                    'post.view', 'post.create', 'post.edit',
                    'stream.view', 'stream.create'
                ]
            ],
            [
                'name' => 'premium',
                'displayName' => 'Премиум пользователь',
                'description' => 'Премиум функции и возможности',
                'permissions' => [
                    'video.view', 'video.create',
                    'category.view',
                    'tag.view',
                    'model.view',
                    'channel.view',
                    'comment.view',
                    'playlist.view',
                    'stream.view', 'stream.create'
                ]
            ],
            [
                'name' => 'user',
                'displayName' => 'Пользователь',
                'description' => 'Обычный зарегистрированный пользователь',
                'permissions' => [
                    'video.view', 'video.create',
                    'category.view',
                    'tag.view',
                    'model.view',
                    'channel.view',
                    'comment.view',
                    'playlist.view',
                    'stream.view'
                ]
            ]
        ];

        $created = 0;
        $updated = 0;

        foreach ($roles as $roleData) {
            $role = $this->roleRepository->findOneBy(['name' => $roleData['name']]);
            
            if ($role) {
                // Обновляем существующую роль
                $role->setDisplayName($roleData['displayName']);
                $role->setDescription($roleData['description']);
                $role->setUpdatedAt(new \DateTimeImmutable());
                $updated++;
            } else {
                // Создаем новую роль
                $role = new Role();
                $role->setName($roleData['name']);
                $role->setDisplayName($roleData['displayName']);
                $role->setDescription($roleData['description']);
                $role->setActive(true);
                $created++;
            }

            // Очищаем текущие разрешения
            $role->getPermissions()->clear();

            // Назначаем разрешения
            if ($roleData['permissions'] === 'all') {
                // Супер админ получает все разрешения
                $allPermissions = $this->permissionRepository->findActivePermissions();
                foreach ($allPermissions as $permission) {
                    $role->addPermission($permission);
                }
            } else {
                // Назначаем указанные разрешения
                foreach ($roleData['permissions'] as $permissionName) {
                    $permission = $this->permissionRepository->findOneBy(['name' => $permissionName]);
                    if ($permission && $permission->isActive()) {
                        $role->addPermission($permission);
                    }
                }
            }
            
            $this->em->persist($role);
        }

        $this->em->flush();

        $io->success(sprintf(
            'Роли инициализированы: создано %d, обновлено %d',
            $created,
            $updated
        ));

        return Command::SUCCESS;
    }
}
