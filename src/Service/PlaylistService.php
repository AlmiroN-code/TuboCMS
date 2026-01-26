<?php

namespace App\Service;

use App\Entity\Channel;
use App\Entity\ChannelPlaylist;
use App\Entity\PlaylistVideo;
use App\Entity\User;
use App\Entity\Video;
use App\Repository\ChannelPlaylistRepository;
use App\Repository\PlaylistVideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class PlaylistService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelPlaylistRepository $playlistRepository,
        private PlaylistVideoRepository $playlistVideoRepository,
        private SluggerInterface $slugger
    ) {}

    /**
     * Создать новый плейлист
     */
    public function createPlaylist(
        Channel $channel,
        string $title,
        ?string $description = null,
        string $visibility = ChannelPlaylist::VISIBILITY_PUBLIC
    ): ChannelPlaylist {
        $playlist = new ChannelPlaylist();
        $playlist->setChannel($channel);
        $playlist->setTitle($title);
        $playlist->setDescription($description);
        $playlist->setVisibility($visibility);
        $playlist->setSortOrder($this->playlistRepository->getNextSortOrder($channel));
        $playlist->generateSlug($this->slugger);

        // Проверка уникальности slug
        $originalSlug = $playlist->getSlug();
        $counter = 1;
        while (!$this->playlistRepository->isSlugUnique($playlist->getSlug())) {
            $playlist->setSlug($originalSlug . '-' . $counter);
            $counter++;
        }

        $this->entityManager->persist($playlist);
        $this->entityManager->flush();

        return $playlist;
    }

    /**
     * Добавить видео в плейлист
     */
    public function addVideoToPlaylist(ChannelPlaylist $playlist, Video $video, ?User $addedBy = null): PlaylistVideo
    {
        // Проверяем, что видео еще не в плейлисте
        if ($this->playlistVideoRepository->isVideoInPlaylist($playlist, $video)) {
            throw new \InvalidArgumentException('Видео уже добавлено в этот плейлист');
        }

        // Проверяем, что видео принадлежит тому же каналу
        if ($video->getChannel() !== $playlist->getChannel()) {
            throw new \InvalidArgumentException('Можно добавлять только видео своего канала');
        }

        $playlistVideo = new PlaylistVideo();
        $playlistVideo->setPlaylist($playlist);
        $playlistVideo->setVideo($video);
        $playlistVideo->setAddedBy($addedBy);
        $playlistVideo->setSortOrder($this->playlistVideoRepository->getNextSortOrder($playlist));

        $this->entityManager->persist($playlistVideo);

        // Обновляем счетчик видео в плейлисте
        $playlist->setVideosCount($playlist->getVideosCount() + 1);

        $this->entityManager->flush();

        return $playlistVideo;
    }

    /**
     * Удалить видео из плейлиста
     */
    public function removeVideoFromPlaylist(ChannelPlaylist $playlist, Video $video): void
    {
        $playlistVideo = $this->playlistVideoRepository->findByPlaylistAndVideo($playlist, $video);
        
        if (!$playlistVideo) {
            throw new \InvalidArgumentException('Видео не найдено в плейлисте');
        }

        $this->playlistVideoRepository->removeVideoAndReorder($playlistVideo);

        // Обновляем счетчик видео в плейлисте
        $playlist->setVideosCount(max(0, $playlist->getVideosCount() - 1));
        $this->entityManager->flush();
    }

    /**
     * Переместить видео в плейлисте
     */
    public function moveVideoInPlaylist(ChannelPlaylist $playlist, Video $video, int $newPosition): void
    {
        $playlistVideo = $this->playlistVideoRepository->findByPlaylistAndVideo($playlist, $video);
        
        if (!$playlistVideo) {
            throw new \InvalidArgumentException('Видео не найдено в плейлисте');
        }

        $this->playlistVideoRepository->moveVideo($playlistVideo, $newPosition);
    }

    /**
     * Обновить плейлист
     */
    public function updatePlaylist(
        ChannelPlaylist $playlist,
        ?string $title = null,
        ?string $description = null,
        ?string $visibility = null
    ): void {
        if ($title !== null) {
            $playlist->setTitle($title);
            
            // Обновляем slug если изменилось название
            $newSlug = $this->slugger->slug($title)->lower();
            if ($newSlug !== $playlist->getSlug()) {
                $originalSlug = $newSlug;
                $counter = 1;
                while (!$this->playlistRepository->isSlugUnique($newSlug, $playlist->getId())) {
                    $newSlug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                $playlist->setSlug($newSlug);
            }
        }

        if ($description !== null) {
            $playlist->setDescription($description);
        }

        if ($visibility !== null) {
            $playlist->setVisibility($visibility);
        }

        $this->entityManager->flush();
    }

    /**
     * Удалить плейлист
     */
    public function deletePlaylist(ChannelPlaylist $playlist): void
    {
        $this->entityManager->remove($playlist);
        $this->entityManager->flush();
    }

    /**
     * Получить плейлисты канала для пользователя
     */
    public function getChannelPlaylists(Channel $channel, ?User $viewer = null, int $limit = 20, int $offset = 0): array
    {
        return $this->playlistRepository->findByChannel($channel, $viewer, $limit, $offset);
    }

    /**
     * Получить видео плейлиста
     */
    public function getPlaylistVideos(ChannelPlaylist $playlist, int $limit = 50, int $offset = 0): array
    {
        return $this->playlistVideoRepository->findByPlaylist($playlist, $limit, $offset);
    }

    /**
     * Проверить может ли пользователь управлять плейлистом
     */
    public function canUserManagePlaylist(ChannelPlaylist $playlist, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Владелец канала может управлять всеми плейлистами
        if ($playlist->getChannel()->getOwner() === $user) {
            return true;
        }

        // Админы могут управлять любыми плейлистами
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return false;
    }

    /**
     * Проверить может ли пользователь просматривать плейлист
     */
    public function canUserViewPlaylist(ChannelPlaylist $playlist, ?User $user): bool
    {
        // Владелец канала может видеть все плейлисты
        if ($user && $user === $playlist->getChannel()->getOwner()) {
            return true;
        }

        // Проверка видимости
        switch ($playlist->getVisibility()) {
            case ChannelPlaylist::VISIBILITY_PUBLIC:
            case ChannelPlaylist::VISIBILITY_UNLISTED:
                return true;
            
            case ChannelPlaylist::VISIBILITY_PREMIUM:
                return $user && $user->isPremium();
            
            case ChannelPlaylist::VISIBILITY_PRIVATE:
                return false;
            
            default:
                return false;
        }
    }

    /**
     * Получить навигацию по плейлисту для видео
     */
    public function getPlaylistNavigation(ChannelPlaylist $playlist, Video $video): ?array
    {
        $playlistVideo = $this->playlistVideoRepository->findByPlaylistAndVideo($playlist, $video);
        
        if (!$playlistVideo) {
            return null;
        }

        $previousVideo = $this->playlistVideoRepository->getPreviousVideo($playlistVideo);
        $nextVideo = $this->playlistVideoRepository->getNextVideo($playlistVideo);
        $position = $this->playlistVideoRepository->getVideoPosition($playlist, $video);
        $totalVideos = $this->playlistVideoRepository->countByPlaylist($playlist);

        return [
            'playlist' => $playlist,
            'currentPosition' => $position,
            'totalVideos' => $totalVideos,
            'previousVideo' => $previousVideo?->getVideo(),
            'nextVideo' => $nextVideo?->getVideo(),
        ];
    }

    /**
     * Дублировать плейлист
     */
    public function duplicatePlaylist(ChannelPlaylist $originalPlaylist, string $newTitle): ChannelPlaylist
    {
        $newPlaylist = $this->createPlaylist(
            $originalPlaylist->getChannel(),
            $newTitle,
            $originalPlaylist->getDescription(),
            $originalPlaylist->getVisibility()
        );

        // Копируем все видео из оригинального плейлиста
        $playlistVideos = $this->playlistVideoRepository->findByPlaylist($originalPlaylist);
        
        foreach ($playlistVideos as $playlistVideo) {
            $this->addVideoToPlaylist($newPlaylist, $playlistVideo->getVideo());
        }

        return $newPlaylist;
    }

    /**
     * Получить статистику плейлистов канала
     */
    public function getChannelPlaylistStats(Channel $channel): array
    {
        $totalPlaylists = $this->playlistRepository->countByChannel($channel);
        
        $publicPlaylists = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(ChannelPlaylist::class, 'p')
            ->where('p.channel = :channel')
            ->andWhere('p.visibility = :visibility')
            ->andWhere('p.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('visibility', ChannelPlaylist::VISIBILITY_PUBLIC)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $totalViews = $this->entityManager->createQueryBuilder()
            ->select('SUM(p.viewsCount)')
            ->from(ChannelPlaylist::class, 'p')
            ->where('p.channel = :channel')
            ->andWhere('p.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalPlaylists' => $totalPlaylists,
            'publicPlaylists' => $publicPlaylists,
            'totalViews' => (int) ($totalViews ?? 0),
        ];
    }

    /**
     * Обновить счетчики плейлиста
     */
    public function updatePlaylistCounters(ChannelPlaylist $playlist): void
    {
        // Обновляем количество видео
        $videosCount = $this->playlistVideoRepository->countByPlaylist($playlist);
        $playlist->setVideosCount($videosCount);

        $this->entityManager->flush();
    }

    /**
     * Увеличить счетчик просмотров плейлиста
     */
    public function incrementPlaylistViews(ChannelPlaylist $playlist): void
    {
        $playlist->setViewsCount($playlist->getViewsCount() + 1);
        $this->entityManager->flush();
    }
}