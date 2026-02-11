<?php

namespace App\Controller;

use App\Entity\PlaylistSubscription;
use App\Repository\ChannelPlaylistRepository;
use App\Repository\PlaylistSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/playlist/subscribe')]
#[IsGranted('ROLE_USER')]
class PlaylistSubscriptionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelPlaylistRepository $playlistRepository,
        private PlaylistSubscriptionRepository $subscriptionRepository
    ) {}

    #[Route('/{id}', name: 'playlist_subscribe', methods: ['POST'])]
    public function subscribe(int $id): JsonResponse
    {
        $playlist = $this->playlistRepository->find($id);
        
        if (!$playlist) {
            return $this->json(['success' => false, 'message' => 'Плейлист не найден'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        
        // Проверяем, не подписан ли уже
        $existingSubscription = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'playlist' => $playlist
        ]);

        if ($existingSubscription) {
            return $this->json(['success' => false, 'message' => 'Вы уже подписаны на этот плейлист']);
        }

        $subscription = new PlaylistSubscription();
        $subscription->setUser($user);
        $subscription->setPlaylist($playlist);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $subscribersCount = $this->subscriptionRepository->countByPlaylist($playlist);

        return $this->json([
            'success' => true,
            'message' => 'Вы подписались на плейлист',
            'is_subscribed' => true,
            'subscribers_count' => $subscribersCount
        ]);
    }

    #[Route('/{id}', name: 'playlist_unsubscribe', methods: ['DELETE'])]
    public function unsubscribe(int $id): JsonResponse
    {
        $playlist = $this->playlistRepository->find($id);
        
        if (!$playlist) {
            return $this->json(['success' => false, 'message' => 'Плейлист не найден'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        
        $subscription = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'playlist' => $playlist
        ]);

        if (!$subscription) {
            return $this->json(['success' => false, 'message' => 'Вы не подписаны на этот плейлист']);
        }

        $this->entityManager->remove($subscription);
        $this->entityManager->flush();

        $subscribersCount = $this->subscriptionRepository->countByPlaylist($playlist);

        return $this->json([
            'success' => true,
            'message' => 'Вы отписались от плейлиста',
            'is_subscribed' => false,
            'subscribers_count' => $subscribersCount
        ]);
    }

    #[Route('/toggle/{id}', name: 'playlist_subscription_toggle', methods: ['POST'])]
    public function toggle(int $id): JsonResponse
    {
        $playlist = $this->playlistRepository->find($id);
        
        if (!$playlist) {
            return $this->json(['success' => false, 'message' => 'Плейлист не найден'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        
        $subscription = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'playlist' => $playlist
        ]);

        if ($subscription) {
            // Отписываемся
            $this->entityManager->remove($subscription);
            $this->entityManager->flush();
            
            $subscribersCount = $this->subscriptionRepository->countByPlaylist($playlist);
            
            return $this->json([
                'success' => true,
                'message' => 'Вы отписались от плейлиста',
                'is_subscribed' => false,
                'subscribers_count' => $subscribersCount
            ]);
        } else {
            // Подписываемся
            $subscription = new PlaylistSubscription();
            $subscription->setUser($user);
            $subscription->setPlaylist($playlist);

            $this->entityManager->persist($subscription);
            $this->entityManager->flush();
            
            $subscribersCount = $this->subscriptionRepository->countByPlaylist($playlist);
            
            return $this->json([
                'success' => true,
                'message' => 'Вы подписались на плейлист',
                'is_subscribed' => true,
                'subscribers_count' => $subscribersCount
            ]);
        }
    }
}
