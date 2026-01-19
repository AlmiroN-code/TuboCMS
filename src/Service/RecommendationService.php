<?php

namespace App\Service;

use App\Entity\Video;
use App\Repository\VideoRepository;

class RecommendationService
{
    public function __construct(
        private VideoRepository $videoRepository,
    ) {
    }

    /**
     * @return Video[]
     */
    public function getRelatedVideos(Video $video, int $limit = 12): array
    {
        // Используем оптимизированный метод репозитория
        return $this->videoRepository->findRelatedByTagsAndCategory(
            $video->getTags()->toArray(),
            $video->getCategory(),
            $video->getId(),
            $limit
        );
    }
}
