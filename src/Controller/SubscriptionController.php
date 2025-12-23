<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/subscription')]
class SubscriptionController extends AbstractController
{
    #[Route('/toggle/{id}', name: 'subscription_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        User $channel,
        SubscriptionRepository $subRepo,
        EntityManagerInterface $em
    ): Response {
        $subscriber = $this->getUser();
        
        // Нельзя подписаться на себя
        if ($subscriber === $channel) {
            return new Response('Cannot subscribe to yourself', 400);
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
        } else {
            // Подписываемся
            $subscription = new Subscription();
            $subscription->setSubscriber($subscriber);
            $subscription->setChannel($channel);
            $em->persist($subscription);
            $channel->setSubscribersCount($channel->getSubscribersCount() + 1);
            $isSubscribed = true;
        }

        $em->flush();

        return $this->render('partials/_subscribe_button.html.twig', [
            'channel' => $channel,
            'is_subscribed' => $isSubscribed,
        ]);
    }
}
