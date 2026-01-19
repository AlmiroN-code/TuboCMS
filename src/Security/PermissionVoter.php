<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Поддерживаем атрибуты, начинающиеся с PERMISSION_
        return str_starts_with($attribute, 'PERMISSION_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        // Пользователь должен быть авторизован
        if (!$user instanceof User) {
            return false;
        }

        // Извлекаем имя права из атрибута (убираем префикс PERMISSION_)
        $permissionName = substr($attribute, 11); // strlen('PERMISSION_') = 11

        // Проверяем, есть ли у пользователя это право
        return $user->hasPermission($permissionName);
    }
}
