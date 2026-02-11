<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\LiveStream;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class LiveStreamVoter extends Voter
{
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::VIEW])
            && $subject instanceof LiveStream;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var LiveStream $stream */
        $stream = $subject;

        return match($attribute) {
            self::EDIT => $this->canEdit($stream, $user),
            self::DELETE => $this->canDelete($stream, $user),
            self::VIEW => $this->canView($stream, $user),
            default => false,
        };
    }

    private function canEdit(LiveStream $stream, User $user): bool
    {
        // Владелец стрима может редактировать
        if ($stream->getStreamer() === $user) {
            return true;
        }

        // Админы могут редактировать
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return false;
    }

    private function canDelete(LiveStream $stream, User $user): bool
    {
        // Владелец может удалить
        if ($stream->getStreamer() === $user) {
            return true;
        }

        // Админы могут удалять
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return false;
    }

    private function canView(LiveStream $stream, User $user): bool
    {
        // Все могут смотреть live стримы
        if ($stream->isLive()) {
            return true;
        }

        // Владелец может видеть свои стримы
        if ($stream->getStreamer() === $user) {
            return true;
        }

        return false;
    }
}
