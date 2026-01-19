<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Role;
use App\Entity\Permission;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;

class RolePermissionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository
    ) {
    }

    public function assignRoleToUser(User $user, string $roleName): bool
    {
        $role = $this->roleRepository->findByName($roleName);
        if (!$role || !$role->isActive()) {
            return false;
        }

        if (!$user->hasRole($roleName)) {
            $user->addUserRole($role);
            $this->em->flush();
        }

        return true;
    }

    public function assignDefaultRoleToNewUser(User $user): bool
    {
        // Назначаем роль "user" всем новым пользователям
        return $this->assignRoleToUser($user, 'user');
    }

    public function removeRoleFromUser(User $user, string $roleName): bool
    {
        $role = $this->roleRepository->findByName($roleName);
        if (!$role) {
            return false;
        }

        $user->removeUserRole($role);
        $this->em->flush();

        return true;
    }

    public function createRole(string $name, string $displayName, ?string $description = null): Role
    {
        $role = new Role();
        $role->setName($name);
        $role->setDisplayName($displayName);
        $role->setDescription($description);

        $this->em->persist($role);
        $this->em->flush();

        return $role;
    }

    public function createPermission(string $name, string $displayName, string $category, ?string $description = null): Permission
    {
        $permission = new Permission();
        $permission->setName($name);
        $permission->setDisplayName($displayName);
        $permission->setCategory($category);
        $permission->setDescription($description);

        $this->em->persist($permission);
        $this->em->flush();

        return $permission;
    }

    public function assignPermissionToRole(Role $role, Permission $permission): void
    {
        if (!$role->getPermissions()->contains($permission)) {
            $role->addPermission($permission);
            $this->em->flush();
        }
    }

    public function removePermissionFromRole(Role $role, Permission $permission): void
    {
        $role->removePermission($permission);
        $this->em->flush();
    }

    public function initializeDefaultRolesAndPermissions(): void
    {
        // Создаем базовые разрешения
        $permissions = [
            // Управление пользователями
            ['name' => 'user.view', 'display' => 'Просмотр пользователей', 'category' => 'users'],
            ['name' => 'user.create', 'display' => 'Создание пользователей', 'category' => 'users'],
            ['name' => 'user.edit', 'display' => 'Редактирование пользователей', 'category' => 'users'],
            ['name' => 'user.delete', 'display' => 'Удаление пользователей', 'category' => 'users'],
            ['name' => 'user.ban', 'display' => 'Блокировка пользователей', 'category' => 'users'],
            
            // Управление видео
            ['name' => 'video.view', 'display' => 'Просмотр видео', 'category' => 'videos'],
            ['name' => 'video.create', 'display' => 'Загрузка видео', 'category' => 'videos'],
            ['name' => 'video.edit', 'display' => 'Редактирование видео', 'category' => 'videos'],
            ['name' => 'video.delete', 'display' => 'Удаление видео', 'category' => 'videos'],
            ['name' => 'video.moderate', 'display' => 'Модерация видео', 'category' => 'videos'],
            ['name' => 'video.feature', 'display' => 'Рекомендация видео', 'category' => 'videos'],
            
            // Управление комментариями
            ['name' => 'comment.view', 'display' => 'Просмотр комментариев', 'category' => 'comments'],
            ['name' => 'comment.create', 'display' => 'Создание комментариев', 'category' => 'comments'],
            ['name' => 'comment.edit', 'display' => 'Редактирование комментариев', 'category' => 'comments'],
            ['name' => 'comment.delete', 'display' => 'Удаление комментариев', 'category' => 'comments'],
            ['name' => 'comment.moderate', 'display' => 'Модерация комментариев', 'category' => 'comments'],
            
            // Управление категориями
            ['name' => 'category.view', 'display' => 'Просмотр категорий', 'category' => 'categories'],
            ['name' => 'category.create', 'display' => 'Создание категорий', 'category' => 'categories'],
            ['name' => 'category.edit', 'display' => 'Редактирование категорий', 'category' => 'categories'],
            ['name' => 'category.delete', 'display' => 'Удаление категорий', 'category' => 'categories'],
            
            // Управление тегами
            ['name' => 'tag.view', 'display' => 'Просмотр тегов', 'category' => 'tags'],
            ['name' => 'tag.create', 'display' => 'Создание тегов', 'category' => 'tags'],
            ['name' => 'tag.edit', 'display' => 'Редактирование тегов', 'category' => 'tags'],
            ['name' => 'tag.delete', 'display' => 'Удаление тегов', 'category' => 'tags'],
            
            // Администрирование
            ['name' => 'admin.access', 'display' => 'Доступ к админ-панели', 'category' => 'admin'],
            ['name' => 'admin.settings', 'display' => 'Управление настройками сайта', 'category' => 'admin'],
            ['name' => 'admin.system', 'display' => 'Системное администрирование', 'category' => 'admin'],
            ['name' => 'admin.roles', 'display' => 'Управление ролями', 'category' => 'admin'],
            ['name' => 'admin.permissions', 'display' => 'Управление разрешениями', 'category' => 'admin'],
        ];

        foreach ($permissions as $permData) {
            $existing = $this->permissionRepository->findOneBy(['name' => $permData['name']]);
            if (!$existing) {
                $this->createPermission($permData['name'], $permData['display'], $permData['category']);
            }
        }

        // Создаем базовые роли
        $roles = [
            ['name' => 'admin', 'display' => 'Администратор', 'description' => 'Полный доступ ко всем функциям'],
            ['name' => 'moderator', 'display' => 'Модератор', 'description' => 'Модерация контента и пользователей'],
            ['name' => 'creator', 'display' => 'Создатель контента', 'description' => 'Расширенные возможности для создателей'],
            ['name' => 'premium', 'display' => 'Премиум пользователь', 'description' => 'Премиум функции'],
            ['name' => 'user', 'display' => 'Пользователь', 'description' => 'Обычный зарегистрированный пользователь'],
        ];

        foreach ($roles as $roleData) {
            $existing = $this->roleRepository->findByName($roleData['name']);
            if (!$existing) {
                $this->createRole($roleData['name'], $roleData['display'], $roleData['description']);
            }
        }

        // Назначаем разрешения ролям
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        $adminRole = $this->roleRepository->findByName('admin');
        $moderatorRole = $this->roleRepository->findByName('moderator');
        $creatorRole = $this->roleRepository->findByName('creator');
        $premiumRole = $this->roleRepository->findByName('premium');

        // Администратор получает все разрешения
        if ($adminRole) {
            $allPermissions = $this->permissionRepository->findActivePermissions();
            foreach ($allPermissions as $permission) {
                $this->assignPermissionToRole($adminRole, $permission);
            }
        }

        // Модератор получает разрешения на модерацию
        if ($moderatorRole) {
            $moderatorPermissions = [
                'admin.access', 'user.view', 'user.ban',
                'video.view', 'video.moderate', 'video.delete',
                'comment.view', 'comment.moderate', 'comment.delete',
                'category.view', 'tag.view'
            ];
            
            foreach ($moderatorPermissions as $permName) {
                $permission = $this->permissionRepository->findOneBy(['name' => $permName]);
                if ($permission) {
                    $this->assignPermissionToRole($moderatorRole, $permission);
                }
            }
        }

        // Создатель контента
        if ($creatorRole) {
            $creatorPermissions = [
                'video.view', 'video.create', 'video.edit', 'video.feature',
                'comment.view', 'comment.create', 'comment.edit',
                'category.view', 'tag.view', 'tag.create'
            ];
            
            foreach ($creatorPermissions as $permName) {
                $permission = $this->permissionRepository->findOneBy(['name' => $permName]);
                if ($permission) {
                    $this->assignPermissionToRole($creatorRole, $permission);
                }
            }
        }

        // Премиум пользователь
        if ($premiumRole) {
            $premiumPermissions = [
                'video.view', 'video.create', 'video.edit',
                'comment.view', 'comment.create', 'comment.edit'
            ];
            
            foreach ($premiumPermissions as $permName) {
                $permission = $this->permissionRepository->findOneBy(['name' => $permName]);
                if ($permission) {
                    $this->assignPermissionToRole($premiumRole, $permission);
                }
            }
        }

        // Обычный пользователь
        $userRole = $this->roleRepository->findByName('user');
        if ($userRole) {
            $userPermissions = [
                'video.view', 'video.create',
                'comment.view', 'comment.create',
                'category.view', 'tag.view'
            ];
            
            foreach ($userPermissions as $permName) {
                $permission = $this->permissionRepository->findOneBy(['name' => $permName]);
                if ($permission) {
                    $this->assignPermissionToRole($userRole, $permission);
                }
            }
        }
    }
}