<?php

namespace App\Controller;

use App\Entity\Video;
use App\Entity\VideoLike;
use App\Repository\VideoLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/like')]
class LikeController extends AbstractController
{
    #[Route('/video/{id}/{type}', name: 'video_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function likeVideo(
        Video $video,
        string $type,
        VideoLikeRepository $likeRepo,
        EntityManagerInterface $em
    ): Response {
        if (!in_array($type, [VideoLike::TYPE_LIKE, VideoLike::TYPE_DISLIKE])) {
            return new Response('Invalid type', 400);
        }

        $user = $this->getUser();
        $existingLike = $likeRepo->findByUserAndVideo($user, $video);

        if ($existingLike) {
            if ($existingLike->getType() === $type) {
                // Убираем лайк/дизлайк
                if ($type === VideoLike::TYPE_LIKE) {
                    $video->setLikesCount(max(0, $video->getLikesCount() - 1));
                } else {
                    $video->setDislikesCount(max(0, $video->getDislikesCount() - 1));
                }
                $em->remove($existingLike);
            } else {
                // Меняем тип
                if ($type === VideoLike::TYPE_LIKE) {
                    $video->setLikesCount($video->getLikesCount() + 1);
                    $video->setDislikesCount(max(0, $video->getDislikesCount() - 1));
                } else {
                    $video->setDislikesCount($video->getDislikesCount() + 1);
                    $video->setLikesCount(max(0, $video->getLikesCount() - 1));
                }
                $existingLike->setType($type);
            }
        } else {
            // Новый лайк/дизлайк
            $like = new VideoLike();
            $like->setUser($user);
            $like->setVideo($video);
            $like->setType($type);
            $em->persist($like);

            if ($type === VideoLike::TYPE_LIKE) {
                $video->setLikesCount($video->getLikesCount() + 1);
            } else {
                $video->setDislikesCount($video->getDislikesCount() + 1);
            }
        }

        $em->flush();

        return $this->render('video/_like_buttons.html.twig', [
            'video' => $video,
            'user_like' => $likeRepo->findByUserAndVideo($user, $video),
        ]);
    }
}
