<?php

namespace App\Service;

use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Сервис для отслеживания показов видео (impressions)
 * Показ засчитывается когда карточка видео появляется в viewport пользователя
 */
class ImpressionTracker
{
    public function __construct(
        private VideoRepository $videoRepository,
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Увеличивает счётчик показов для списка видео
     * 
     * @param array<int> $videoIds
     */
    public function trackImpressions(array $videoIds): void
    {
        if (empty($videoIds)) {
            return;
        }

        // Используем bulk update для эффективности
        $this->em->createQuery(
            'UPDATE App\Entity\Video v SET v.impressionsCount = v.impressionsCount + 1 WHERE v.id IN (:ids)'
        )
        ->setParameter('ids', $videoIds)
        ->execute();
    }

    /**
     * Увеличивает счётчик показов для одного видео
     */
    public function trackImpression(int $videoId): void
    {
        $this->trackImpressions([$videoId]);
    }
}
