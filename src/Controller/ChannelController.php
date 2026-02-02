<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\ChannelSubscription;
use App\Entity\User;
use App\Repository\ChannelRepository;
use App\Repository\ChannelSubscriptionRepository;
use App\Repository\VideoRepository;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class ChannelController extends AbstractController
{
    public function __construct(
        private ChannelRepository $channelRepository,
        private ChannelSubscriptionRepository $subscriptionRepository,
        private VideoRepository $videoRepository,
        private EntityManagerInterface $entityManager,
        private SettingsService $settingsService,
        private SluggerInterface $slugger
    ) {}

    #[Route('/channels', name: 'channels_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $this->settingsService->getVideosPerPage()); // Убеждаемся, что limit > 0
        $offset = ($page - 1) * $limit;

        $filters = [
            'search' => $request->query->get('search'),
            'type' => $request->query->get('type'),
            'verified' => $request->query->get('verified') === '1',
            'premium' => $request->query->get('premium') === '1',
            'sort' => $request->query->get('sort', 'popular')
        ];

        $channels = $this->channelRepository->findWithFilters($filters, $limit, $offset);
        $totalChannels = $this->channelRepository->countWithFilters($filters);
        $totalPages = ceil($totalChannels / $limit);

        return $this->render('channel/index.html.twig', [
            'channels' => $channels,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_channels' => $totalChannels,
            'filters' => $filters,
        ]);
    }

    #[Route('/channels/studios', name: 'channels_studios')]
    public function studios(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $channels = $this->channelRepository->findStudios($limit, $offset);
        $totalChannels = $this->channelRepository->countStudios();
        $totalPages = ceil($totalChannels / $limit);

        return $this->render('channel/studios.html.twig', [
            'channels' => $channels,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_channels' => $totalChannels,
        ]);
    }

    #[Route('/channels/verified', name: 'channels_verified')]
    public function verified(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $channels = $this->channelRepository->findVerified($limit, $offset);
        $totalChannels = $this->channelRepository->countWithFilters(['verified' => true]);
        $totalPages = ceil($totalChannels / $limit);

        return $this->render('channel/verified.html.twig', [
            'channels' => $channels,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_channels' => $totalChannels,
        ]);
    }

    #[Route('/channel/{slug}', name: 'channel_show')]
    public function show(string $slug, Request $request): Response
    {
        $channel = $this->channelRepository->findBySlug($slug);
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $videos = $this->videoRepository->findByChannel($channel, $limit, $offset);
        $totalVideos = $this->videoRepository->countByChannel($channel);
        $totalPages = ceil($totalVideos / $limit);

        $isSubscribed = false;
        $subscription = null;
        if ($this->getUser()) {
            $subscription = $this->subscriptionRepository->findSubscription($this->getUser(), $channel);
            $isSubscribed = $subscription !== null;
        }

        return $this->render('channel/show.html.twig', [
            'channel' => $channel,
            'videos' => $videos,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_videos' => $totalVideos,
            'is_subscribed' => $isSubscribed,
            'subscription' => $subscription,
        ]);
    }

    #[Route('/channel/{slug}/about', name: 'channel_about')]
    public function about(string $slug): Response
    {
        $channel = $this->channelRepository->findBySlug($slug);
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        return $this->render('channel/about.html.twig', [
            'channel' => $channel,
        ]);
    }

    #[Route('/channel/{slug}/subscribe', name: 'channel_subscribe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function subscribe(string $slug): JsonResponse
    {
        $channel = $this->channelRepository->findBySlug($slug);
        if (!$channel) {
            return new JsonResponse(['error' => 'Канал не найден'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        $existingSubscription = $this->subscriptionRepository->findSubscription($user, $channel);
        if ($existingSubscription) {
            return new JsonResponse(['error' => 'Вы уже подписаны на этот канал'], 400);
        }

        $subscription = new ChannelSubscription();
        $subscription->setUser($user);
        $subscription->setChannel($channel);

        $this->entityManager->persist($subscription);

        // Обновить счетчик подписчиков
        $channel->setSubscribersCount($channel->getSubscribersCount() + 1);
        
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'subscribers_count' => $channel->getSubscribersCount()
        ]);
    }

    #[Route('/channel/{slug}/unsubscribe', name: 'channel_unsubscribe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unsubscribe(string $slug): JsonResponse
    {
        $channel = $this->channelRepository->findBySlug($slug);
        if (!$channel) {
            return new JsonResponse(['error' => 'Канал не найден'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        $subscription = $this->subscriptionRepository->findSubscription($user, $channel);
        if (!$subscription) {
            return new JsonResponse(['error' => 'Вы не подписаны на этот канал'], 400);
        }

        $this->entityManager->remove($subscription);

        // Обновить счетчик подписчиков
        $channel->setSubscribersCount(max(0, $channel->getSubscribersCount() - 1));
        
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'subscribers_count' => $channel->getSubscribersCount()
        ]);
    }

    #[Route('/my-channels', name: 'my_channels')]
    #[IsGranted('ROLE_USER')]
    public function myChannels(): Response
    {
        // Перенаправляем на новую систему профилей с вкладками
        return $this->redirectToRoute('user_profile_channels', ['username' => $this->getUser()->getUsername()]);
    }

    #[Route('/create-channel', name: 'channel_create')]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Проверка CSRF токена
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('channel_create', $token)) {
                $this->addFlash('error', 'Недействительный CSRF токен');
                return $this->render('channel/create.html.twig');
            }
            
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $type = $request->request->get('type', Channel::TYPE_PERSONAL);

            if (!$name) {
                $this->addFlash('error', 'Название канала обязательно');
                return $this->redirectToRoute('channel_create');
            }

            /** @var User $user */
            $user = $this->getUser();

            $channel = new Channel();
            $channel->setName($name);
            $channel->setDescription($description);
            $channel->setType($type);
            $channel->setOwner($user);
            $channel->generateSlug($this->slugger);

            // Проверка уникальности slug
            $originalSlug = $channel->getSlug();
            $counter = 1;
            while (!$this->channelRepository->isSlugUnique($channel->getSlug())) {
                $channel->setSlug($originalSlug . '-' . $counter);
                $counter++;
            }

            $this->entityManager->persist($channel);
            $this->entityManager->flush();

            $this->addFlash('success', 'Канал успешно создан');
            return $this->redirectToRoute('channel_show', ['slug' => $channel->getSlug()]);
        }

        return $this->render('channel/create.html.twig');
    }
}