<?php

namespace App\Controller;

use App\Entity\ModelLike;
use App\Entity\ModelProfile;
use App\Repository\ModelLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/model/like')]
class ModelLikeController extends AbstractController
{
    /**
     * Переключение лайка/дизлайка модели
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6
     */
    #[Route('/{id}/{type}', name: 'model_like_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        ModelProfile $model,
        string $type,
        ModelLikeRepository $likeRepo,
        EntityManagerInterface $em
    ): Response {
        // Валидация типа
        if (!in_array($type, [ModelLike::TYPE_LIKE, ModelLike::TYPE_DISLIKE], true)) {
            throw new BadRequestHttpException('Invalid like type');
        }

        $user = $this->getUser();
        $existingLike = $likeRepo->findByUserAndModel($user, $model);
        $userLikeType = null;

        if ($existingLike) {
            if ($existingLike->getType() === $type) {
                // Повторный клик на текущую оценку - удаляем (Req 4.4)
                $this->decrementCounter($model, $type);
                $em->remove($existingLike);
                $userLikeType = null;
            } else {
                // Смена оценки с лайка на дизлайк или наоборот (Req 4.3)
                $this->decrementCounter($model, $existingLike->getType());
                $existingLike->setType($type);
                $this->incrementCounter($model, $type);
                $userLikeType = $type;
            }
        } else {
            // Новая оценка (Req 4.1, 4.2)
            $like = new ModelLike();
            $like->setUser($user);
            $like->setModel($model);
            $like->setType($type);
            $em->persist($like);
            $this->incrementCounter($model, $type);
            $userLikeType = $type;
        }

        $em->flush();

        // Возвращаем обновлённые кнопки через HTMX (Req 4.6)
        return $this->render('model/_like_buttons.html.twig', [
            'model' => $model,
            'user_like_type' => $userLikeType,
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
