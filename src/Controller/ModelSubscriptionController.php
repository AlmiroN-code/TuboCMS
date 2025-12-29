<?php

namespace App\Controller;

use App\Entity\ModelProfile;
use App\Entity\ModelSubscription;
use App\Repository\ModelSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/model/subscribe')]
class ModelSubscriptionController extends AbstractController
{
    /**
     * Переключение подписки на модель (подписка/отписка)
     * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
     */
    #[Route('/{id}', name: 'model_subscription_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        ModelProfile $model,
        ModelSubscriptionRepository $subscriptionRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        // Проверяем существующую подписку
        $subscription = $subscriptionRepo->findByUserAndModel($user, $model);

        if ($subscription) {
            // Отписываемся - удаляем подписку и уменьшаем счётчик
            $em->remove($subscription);
            $model->setSubscribersCount(max(0, $model->getSubscribersCount() - 1));
            $isSubscribed = false;
        } else {
            // Подписываемся - создаём подписку и увеличиваем счётчик
            $subscription = new ModelSubscription();
            $subscription->setUser($user);
            $subscription->setModel($model);
            $em->persist($subscription);
            $model->setSubscribersCount($model->getSubscribersCount() + 1);
            $isSubscribed = true;
        }

        $em->flush();

        // Возвращаем обновлённую кнопку через HTMX
        return $this->render('model/_subscribe_button.html.twig', [
            'model' => $model,
            'is_subscribed' => $isSubscribed,
        ]);
    }
}
