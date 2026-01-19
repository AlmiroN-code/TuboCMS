<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/subscription')]
class SubscriptionController extends AbstractController
{
    #[Route('/toggle/{id}', name: 'subscription_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        User $channel,
        SubscriptionRepository $subRepo,
        EntityManagerInterface $em,
        TranslatorInterface $translator
    ): JsonResponse {
        $subscriber = $this->getUser();
        
        // Нельзя подписаться на себя
        if ($subscriber === $channel) {
            return $this->json(['error' => 'Cannot subscribe to yourself'], 400);
        }

        $subscription = $subRepo->findOneBy([
            'subscriber' => $subscriber,
            'channel' => $channel
        ]);

        if ($subscription) {
            // Отписываемся
            $em->remove($subscription);
            $channel->setSubscribersCount(max(0, $channel->getSubscribersCount() - 1));
            $isSubscribed = false;
            $message = $translator->trans('toast.unsubscribed', [], 'messages');
        } else {
            // Подписываемся
            $subscription = new Subscription();
            $subscription->setSubscriber($subscriber);
            $subscription->setChannel($channel);
            $em->persist($subscription);
            $channel->setSubscribersCount($channel->getSubscribersCount() + 1);
            $isSubscribed = true;
            $message = $translator->trans('toast.subscribed', [], 'messages');
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'isSubscribed' => $isSubscribed,
            'subscribersCount' => $channel->getSubscribersCount(),
            'message' => $message,
        ]);
    }
}
