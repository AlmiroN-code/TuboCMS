<?php

namespace App\Twig;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_permission', [$this, 'hasPermission']),
            new TwigFunction('has_role', [$this, 'hasRole']),
            new TwigFunction('current_user', [$this, 'getCurrentUser']),
        ];
    }

    public function hasPermission(string $permission): bool
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return false;
        }

        return $user->hasPermission($permission);
    }

    public function hasRole(string $role): bool
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return false;
        }

        return $user->hasRole($role);
    }

    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();
        
        return $user instanceof User ? $user : null;
    }
}