<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\VideoLike;
use App\Repository\VideoLikeRepository;
use Doctrine\ORM\EntityManagerInterface;

class LikeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VideoLikeRepository $videoLikeRepository,
    ) {
    }

    public function like(User $user, Video $video): void
    {
        $existing = $this->videoLikeRepository->findByUserAndVideo($user, $video);

        if ($existing !== null) {
            if ($existing->isLike()) {
                // Already liked - remove like (toggle)
                $this->removeLike($user, $video);
                return;
            }
            // Was dislike - switch to like
            $existing->setIsLike(true);
            $video->setLikesCount($video->getLikesCount() + 1);
            $video->setDislikesCount(max(0, $video->getDislikesCount() - 1));
            $this->em->flush();
            return;
        }

        // New like
        $like = new VideoLike();
        $like->setUser($user);
        $like->setVideo($video);
        $like->setIsLike(true);

        $video->setLikesCount($video->getLikesCount() + 1);

        $this->em->persist($like);
        $this->em->flush();
    }

    public function dislike(User $user, Video $video): void
    {
        $existing = $this->videoLikeRepository->findByUserAndVideo($user, $video);

        if ($existing !== null) {
            if ($existing->isDislike()) {
                // Already disliked - remove (toggle)
                $this->removeLike($user, $video);
                return;
            }
            // Was like - switch to dislike
            $existing->setIsLike(false);
            $video->setDislikesCount($video->getDislikesCount() + 1);
            $video->setLikesCount(max(0, $video->getLikesCount() - 1));
            $this->em->flush();
            return;
        }

        // New dislike
        $dislike = new VideoLike();
        $dislike->setUser($user);
        $dislike->setVideo($video);
        $dislike->setIsLike(false);

        $video->setDislikesCount($video->getDislikesCount() + 1);

        $this->em->persist($dislike);
        $this->em->flush();
    }

    public function removeLike(User $user, Video $video): void
    {
        $existing = $this->videoLikeRepository->findByUserAndVideo($user, $video);
        if ($existing === null) {
            return;
        }

        if ($existing->isLike()) {
            $video->setLikesCount(max(0, $video->getLikesCount() - 1));
        } else {
            $video->setDislikesCount(max(0, $video->getDislikesCount() - 1));
        }

        $this->em->remove($existing);
        $this->em->flush();
    }

    /**
     * @return bool|null true = like, false = dislike, null = no reaction
     */
    public function getUserReaction(User $user, Video $video): ?bool
    {
        $existing = $this->videoLikeRepository->findByUserAndVideo($user, $video);
        return $existing?->isLike();
    }

    /**
     * @return VideoLike[]
     */
    public function getLikedVideos(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        return $this->videoLikeRepository->findLikedByUser($user, $limit, $offset);
    }
}
