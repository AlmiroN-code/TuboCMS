<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/push')]
class PushNotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    #[Route('/subscribe', name: 'api_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Invalid subscription data'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Проверяем существующую подписку
        $subscription = $this->em->getRepository(PushSubscription::class)
            ->findOneBy(['endpoint' => $data['endpoint']]);

        if (!$subscription) {
            $subscription = new PushSubscription();
            $subscription->setEndpoint($data['endpoint']);
        }

        $subscription->setUser($user);
        $subscription->setP256dh($data['keys']['p256dh'] ?? null);
        $subscription->setAuth($data['keys']['auth'] ?? null);
        $subscription->setUserAgent($request->headers->get('User-Agent'));

        $this->em->persist($subscription);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/unsubscribe', name: 'api_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        $subscription = $this->em->getRepository(PushSubscription::class)
            ->findOneBy(['endpoint' => $data['endpoint']]);

        if ($subscription) {
            $this->em->remove($subscription);
            $this->em->flush();
        }

        return new JsonResponse(['success' => true]);
    }
}
