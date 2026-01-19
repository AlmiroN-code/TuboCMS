<?php

namespace App\Controller;

use App\Entity\ModelLike;
use App\Entity\ModelProfile;
use App\Repository\ModelLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/model/like')]
class ModelLikeController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Переключение лайка/дизлайка модели
     */
    #[Route('/{id}/{type}', name: 'model_like_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        ModelProfile $model,
        string $type,
        ModelLikeRepository $likeRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        // Валидация типа
        if (!in_array($type, [ModelLike::TYPE_LIKE, ModelLike::TYPE_DISLIKE], true)) {
            throw new BadRequestHttpException('Invalid like type');
        }

        $user = $this->getUser();
        $existingLike = $likeRepo->findByUserAndModel($user, $model);
        $userLikeType = null;

        if ($existingLike) {
            if ($existingLike->getType() === $type) {
                // Повторный клик на текущую оценку - удаляем
                $this->decrementCounter($model, $type);
                $em->remove($existingLike);
                $userLikeType = null;
            } else {
                // Смена оценки с лайка на дизлайк или наоборот
                $this->decrementCounter($model, $existingLike->getType());
                $existingLike->setType($type);
                $this->incrementCounter($model, $type);
                $userLikeType = $type;
            }
        } else {
            // Новая оценка
            $like = new ModelLike();
            $like->setUser($user);
            $like->setModel($model);
            $like->setType($type);
            $em->persist($like);
            $this->incrementCounter($model, $type);
            $userLikeType = $type;
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'likesCount' => $model->getLikesCount(),
            'dislikesCount' => $model->getDislikesCount(),
            'userLike' => $userLikeType,
            'message' => $this->translator->trans('toast.vote_success', [], 'messages'),
        ]);
    }

    private function incrementCounter(ModelProfile $model, string $type): void
    {
        if ($type === ModelLike::TYPE_LIKE) {
            $model->setLikesCount($model->getLikesCount() + 1);
        } else {
            $model->setDislikesCount($model->getDislikesCount() + 1);
        }
    }

    private function decrementCounter(ModelProfile $model, string $type): void
    {
        if ($type === ModelLike::TYPE_LIKE) {
            $model->setLikesCount(max(0, $model->getLikesCount() - 1));
        } else {
            $model->setDislikesCount(max(0, $model->getDislikesCount() - 1));
        }
    }
}
