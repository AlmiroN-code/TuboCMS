<?php

namespace App\Service;

use App\Entity\Playlist;
use App\Entity\PlaylistVideo;
use App\Entity\User;
use App\Entity\Video;
use App\Repository\PlaylistRepository;
use App\Repository\PlaylistVideoRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlaylistService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlaylistRepository $playlistRepository,
        private PlaylistVideoRepository $playlistVideoRepository,
    ) {
    }

    public function create(User $user, string $title, ?string $description = null, bool $isPublic = true): Playlist
    {
        $playlist = new Playlist();
        $playlist->setOwner($user);
        $playlist->setTitle($title);
        $playlist->setDescription($description);
        $playlist->setIsPublic($isPublic);

        $this->em->persist($playlist);
        $this->em->flush();

        return $playlist;
    }

    public function update(Playlist $playlist, string $title, ?string $description = null, ?bool $isPublic = null): Playlist
    {
        $playlist->setTitle($title);
        $playlist->setDescription($description);
        
        if ($isPublic !== null) {
            $playlist->setIsPublic($isPublic);
        }
        
        $playlist->updateTimestamp();
        $this->em->flush();

        return $playlist;
    }

    public function delete(Playlist $playlist): void
    {
        $this->em->remove($playlist);
        $this->em->flush();
    }

    public function addVideo(Playlist $playlist, Video $video): void
    {
        $existing = $this->playlistVideoRepository->findByPlaylistAndVideo($playlist, $video);
        if ($existing !== null) {
            return; // Already in playlist
        }

        $position = $this->playlistVideoRepository->getMaxPosition($playlist) + 1;

        $playlistVideo = new PlaylistVideo();
        $playlistVideo->setPlaylist($playlist);
        $playlistVideo->setVideo($video);
        $playlistVideo->setPosition($position);

        $playlist->incrementVideosCount();
        $playlist->updateTimestamp();

        $this->em->persist($playlistVideo);
        $this->em->flush();
    }

    public function removeVideo(Playlist $playlist, Video $video): void
    {
        $playlistVideo = $this->playlistVideoRepository->findByPlaylistAndVideo($playlist, $video);
        if ($playlistVideo === null) {
            return;
        }

        $this->em->remove($playlistVideo);
        $playlist->decrementVideosCount();
        $playlist->updateTimestamp();
        $this->em->flush();
    }

    /**
     * @param int[] $videoIds Ordered array of video IDs
     */
    public function reorderVideos(Playlist $playlist, array $videoIds): void
    {
        $playlistVideos = $this->playlistVideoRepository->findByPlaylist($playlist);
        $videoMap = [];
        
        foreach ($playlistVideos as $pv) {
            $videoMap[$pv->getVideo()->getId()] = $pv;
        }

        $position = 0;
        foreach ($videoIds as $videoId) {
            if (isset($videoMap[$videoId])) {
                $videoMap[$videoId]->setPosition($position);
                $position++;
            }
        }

        $playlist->updateTimestamp();
        $this->em->flush();
    }

    /**
     * @return Playlist[]
     */
    public function getUserPlaylists(User $user, int $limit = 50, int $offset = 0): array
    {
        return $this->playlistRepository->findByOwner($user, $limit, $offset);
    }

    /**
     * @return Playlist[]
     */
    public function getPublicPlaylists(User $user, int $limit = 50, int $offset = 0): array
    {
        return $this->playlistRepository->findPublicByOwner($user, $limit, $offset);
    }

    public function countUserPlaylists(User $user): int
    {
        return $this->playlistRepository->countByOwner($user);
    }
}
