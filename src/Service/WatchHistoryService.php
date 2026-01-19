<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\WatchHistory;
use App\Repository\WatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class WatchHistoryService
{
    private const MIN_WATCH_SECONDS = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private WatchHistoryRepository $watchHistoryRepository,
    ) {
    }

    public function record(User $user, Video $video, int $seconds): void
    {
        if ($seconds < self::MIN_WATCH_SECONDS) {
            return;
        }

        $history = $this->watchHistoryRepository->findByUserAndVideo($user, $video);

        if ($history === null) {
            $history = new WatchHistory();
            $history->setUser($user);
            $history->setVideo($video);
            $this->em->persist($history);
        }

        $history->setWatchedSeconds($seconds);
        
        $duration = $video->getDuration();
        if ($duration > 0) {
            $progress = min(100, (int) round(($seconds / $duration) * 100));
            $history->setWatchProgress($progress);
        }

        $history->updateWatchedAt();
        $this->em->flush();
    }

    /**
     * @return WatchHistory[]
     */
    public function getHistory(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        return $this->watchHistoryRepository->findByUser($user, $limit, $offset);
    }

    public function deleteEntry(User $user, Video $video): void
    {
        $this->watchHistoryRepository->deleteByUserAndVideo($user, $video);
    }

    public function clearHistory(User $user): void
    {
        $this->watchHistoryRepository->deleteByUser($user);
    }

    public function getWatchProgress(User $user, Video $video): ?int
    {
        $history = $this->watchHistoryRepository->findByUserAndVideo($user, $video);
        return $history?->getWatchProgress();
    }

    public function countHistory(User $user): int
    {
        return $this->watchHistoryRepository->countByUser($user);
    }
}
