<?php

namespace App\Controller;

use App\Entity\Video;
use App\Repository\VideoLikeRepository;
use App\Service\LikeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/like')]
class LikeController extends AbstractController
{
    public function __construct(
        private LikeService $likeService,
        private VideoLikeRepository $likeRepo,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/video/{id}/like', name: 'video_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(Video $video): JsonResponse
    {
        $user = $this->getUser();
        $this->likeService->like($user, $video);
        $userLike = $this->likeRepo->findByUserAndVideo($user, $video);

        return $this->json([
            'success' => true,
            'likesCount' => $video->getLikesCount(),
            'dislikesCount' => $video->getDislikesCount(),
            'userLike' => $userLike ? ($userLike->isLike() ? 'like' : 'dislike') : null,
            'message' => $this->translator->trans('toast.vote_success', [], 'messages'),
        ]);
    }

    #[Route('/video/{id}/dislike', name: 'video_dislike', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function dislike(Video $video): JsonResponse
    {
        $user = $this->getUser();
        $this->likeService->dislike($user, $video);
        $userLike = $this->likeRepo->findByUserAndVideo($user, $video);

        return $this->json([
            'success' => true,
            'likesCount' => $video->getLikesCount(),
            'dislikesCount' => $video->getDislikesCount(),
            'userLike' => $userLike ? ($userLike->isLike() ? 'like' : 'dislike') : null,
            'message' => $this->translator->trans('toast.vote_success', [], 'messages'),
        ]);
    }

    #[Route('/video/{id}', name: 'video_like_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function remove(Video $video): JsonResponse
    {
        $user = $this->getUser();
        $this->likeService->removeLike($user, $video);

        return $this->json([
            'success' => true,
            'likesCount' => $video->getLikesCount(),
            'dislikesCount' => $video->getDislikesCount(),
            'userLike' => null,
            'message' => $this->translator->trans('toast.vote_removed', [], 'messages'),
        ]);
    }

    // Legacy route for backward compatibility
    #[Route('/video/{id}/{type}', name: 'video_like_legacy', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function legacyLike(Video $video, string $type): JsonResponse
    {
        $user = $this->getUser();
        
        if ($type === 'like') {
            $this->likeService->like($user, $video);
        } else {
            $this->likeService->dislike($user, $video);
        }
        
        $userLike = $this->likeRepo->findByUserAndVideo($user, $video);

        return $this->json([
            'success' => true,
            'likesCount' => $video->getLikesCount(),
            'dislikesCount' => $video->getDislikesCount(),
            'userLike' => $userLike ? ($userLike->isLike() ? 'like' : 'dislike') : null,
            'message' => $this->translator->trans('toast.vote_success', [], 'messages'),
        ]);
    }
}
