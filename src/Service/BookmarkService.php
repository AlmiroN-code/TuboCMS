<?php

namespace App\Service;

use App\Entity\Bookmark;
use App\Entity\User;
use App\Entity\Video;
use App\Repository\BookmarkRepository;
use Doctrine\ORM\EntityManagerInterface;

class BookmarkService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookmarkRepository $bookmarkRepository,
    ) {
    }

    public function add(User $user, Video $video): void
    {
        if ($this->isBookmarked($user, $video)) {
            return;
        }

        $bookmark = new Bookmark();
        $bookmark->setUser($user);
        $bookmark->setVideo($video);

        $this->em->persist($bookmark);
        $this->em->flush();
    }

    public function remove(User $user, Video $video): void
    {
        $this->bookmarkRepository->deleteByUserAndVideo($user, $video);
    }

    public function toggle(User $user, Video $video): bool
    {
        if ($this->isBookmarked($user, $video)) {
            $this->remove($user, $video);
            return false;
        }
        
        $this->add($user, $video);
        return true;
    }

    /**
     * @return Bookmark[]
     */
    public function getBookmarks(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        return $this->bookmarkRepository->findByUser($user, $limit, $offset);
    }

    public function isBookmarked(User $user, Video $video): bool
    {
        return $this->bookmarkRepository->isBookmarked($user, $video);
    }

    public function countBookmarks(User $user): int
    {
        return $this->bookmarkRepository->countByUser($user);
    }
}
