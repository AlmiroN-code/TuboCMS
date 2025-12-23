<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter
{
    public const PERMISSION_PREFIX = 'PERMISSION_';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, self::PERMISSION_PREFIX);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Извлекаем имя разрешения из атрибута
        $permission = substr($attribute, strlen(self::PERMISSION_PREFIX));

        return $user->hasPermission($permission);
    }
}