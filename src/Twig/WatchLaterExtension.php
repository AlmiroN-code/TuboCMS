<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\WatchLaterRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WatchLaterExtension extends AbstractExtension
{
    public function __construct(
        private readonly WatchLaterRepository $watchLaterRepository,
        private readonly Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_in_watch_later', [$this, 'isInWatchLater']),
            new TwigFunction('watch_later_video_ids', [$this, 'getWatchLaterVideoIds']),
        ];
    }

    /**
     * Проверяет, находится ли видео в списке "Смотреть позже" текущего пользователя
     */
    public function isInWatchLater(int $videoId): bool
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return false;
        }

        // Используем простой запрос для проверки
        return $this->watchLaterRepository->count([
            'user' => $user,
            'video' => $videoId
        ]) > 0;
    }

    /**
     * Получает массив ID видео в списке "Смотреть позже" для текущего пользователя
     */
    public function getWatchLaterVideoIds(): array
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return [];
        }

        return $this->watchLaterRepository->getUserWatchLaterVideoIds($user);
    }
}
