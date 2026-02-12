<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ChannelSubscription;
use App\Entity\ModelSubscription;
use App\Entity\PlaylistSubscription;
use App\Form\ProfileEditType;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use App\Repository\BookmarkRepository;
use App\Repository\WatchLaterRepository;
use App\Repository\ChannelRepository;
use App\Repository\SeriesRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ChannelPlaylistRepository;
use App\Repository\NotificationRepository;
use App\Repository\WatchHistoryRepository;
use App\Service\UserStatsService;
use App\Service\SettingsService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/members/{username}')]
class UserProfileController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private VideoRepository $videoRepository,
        private BookmarkRepository $bookmarkRepository,
        private WatchLaterRepository $watchLaterRepository,
        private WatchHistoryRepository $watchHistoryRepository,
        private ChannelRepository $channelRepository,
        private SeriesRepository $seriesRepository,
        private SubscriptionRepository $subscriptionRepository,
        private ChannelPlaylistRepository $playlistRepository,
        private NotificationRepository $notificationRepository,
        private UserStatsService $userStatsService,
        private SettingsService $settingsService,
        private EntityManagerInterface $entityManager,
        private ImageService $imageService
    ) {}

    #[Route('', name: 'user_profile_overview')]
    public function overview(string $username): Response
    {
        $user = $this->findUserByUsername($username);
        
        // Получаем статистику и видео через сервис с кешированием
        $stats = $this->userStatsService->getUserStats($user);
        $recentVideos = $this->userStatsService->getRecentVideos($user, 6);
        
        // Проверяем подписку
        $isSubscribed = false;
        if ($this->getUser() && $this->getUser() !== $user) {
            $isSubscribed = $this->subscriptionRepository->findOneBy([
                'subscriber' => $this->getUser(),
                'channel' => $user
            ]) !== null;
        }
        
        return $this->render('members/profile/overview.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'recent_videos' => $recentVideos,
            'is_subscribed' => $isSubscribed,
            'active_tab' => 'overview'
        ]);
    }

    #[Route('/videos', name: 'user_profile_videos')]
    public function videos(string $username, Request $request): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkProfileAccess($user, 'videos');

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $videos = $this->videoRepository->findBy(
            ['createdBy' => $user],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );

        $totalVideos = $this->videoRepository->count(['createdBy' => $user]);
        $totalPages = ceil($totalVideos / $limit);

        return $this->render('members/profile/videos.html.twig', [
            'user' => $user,
            'videos' => $videos,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_videos' => $totalVideos,
            'active_tab' => 'videos'
        ]);
    }

    #[Route('/bookmarks', name: 'user_profile_bookmarks')]
    #[IsGranted('ROLE_USER')]
    public function bookmarks(string $username, Request $request): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkOwnerAccess($user);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $bookmarks = $this->bookmarkRepository->findByUser($user, $limit, $offset);
        $totalBookmarks = $this->bookmarkRepository->countByUser($user);
        $totalPages = ceil($totalBookmarks / $limit);

        return $this->render('members/profile/bookmarks.html.twig', [
            'user' => $user,
            'bookmarks' => $bookmarks,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_bookmarks' => $totalBookmarks,
            'active_tab' => 'bookmarks'
        ]);
    }

    #[Route('/watch-later', name: 'user_profile_watch_later')]
    #[IsGranted('ROLE_USER')]
    public function watchLater(string $username, Request $request): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkOwnerAccess($user);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();

        $watchLaterItems = $this->watchLaterRepository->findUserWatchLater($user, $page, $limit);
        $total = $this->watchLaterRepository->countUserWatchLater($user);
        $totalPages = ceil($total / $limit);

        // Извлекаем видео из WatchLater entities
        $videos = array_map(fn($item) => $item->getVideo(), $watchLaterItems);

        return $this->render('members/profile/watch_later.html.twig', [
            'user' => $user,
            'videos' => $videos,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'active_tab' => 'watch_later'
        ]);
    }

    #[Route('/history', name: 'user_profile_history')]
    #[IsGranted('ROLE_USER')]
    public function history(string $username, Request $request): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkOwnerAccess($user);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $historyItems = $this->watchHistoryRepository->findByUser($user, $limit, $offset);
        $total = $this->watchHistoryRepository->countByUser($user);
        $totalPages = ceil($total / $limit);

        // Извлекаем видео из WatchHistory entities
        $videos = array_map(fn($item) => $item->getVideo(), $historyItems);

        return $this->render('members/profile/history.html.twig', [
            'user' => $user,
            'videos' => $videos,
            'history_items' => $historyItems,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'active_tab' => 'history'
        ]);
    }

    #[Route('/channels', name: 'user_profile_channels')]
    public function channels(string $username): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkProfileAccess($user, 'channels');

        $ownedChannels = $this->channelRepository->findByOwner($user);
        $subscriptions = $this->subscriptionRepository->findUserSubscriptions($user);

        return $this->render('members/profile/channels.html.twig', [
            'user' => $user,
            'owned_channels' => $ownedChannels,
            'subscriptions' => $subscriptions,
            'active_tab' => 'channels'
        ]);
    }

    #[Route('/series', name: 'user_profile_series')]
    public function series(string $username): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkProfileAccess($user, 'series');

        $series = $this->seriesRepository->findByAuthor($user);

        return $this->render('members/profile/series.html.twig', [
            'user' => $user,
            'series' => $series,
            'active_tab' => 'series'
        ]);
    }

    #[Route('/subscriptions', name: 'user_profile_subscriptions')]
    public function subscriptions(string $username, Request $request): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkProfileAccess($user, 'subscriptions');

        $type = $request->query->get('type', 'users'); // users, channels, models, playlists
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $subscriptions = [];
        $totalSubscriptions = 0;

        switch ($type) {
            case 'channels':
                $channelSubRepo = $this->entityManager->getRepository(ChannelSubscription::class);
                $subscriptions = $channelSubRepo->findBy(['user' => $user], ['subscribedAt' => 'DESC'], $limit, $offset);
                $totalSubscriptions = $channelSubRepo->count(['user' => $user]);
                break;
            
            case 'models':
                $modelSubRepo = $this->entityManager->getRepository(ModelSubscription::class);
                $subscriptions = $modelSubRepo->findBy(['user' => $user], ['createdAt' => 'DESC'], $limit, $offset);
                $totalSubscriptions = $modelSubRepo->count(['user' => $user]);
                break;
            
            case 'playlists':
                $playlistSubRepo = $this->entityManager->getRepository(PlaylistSubscription::class);
                $subscriptions = $playlistSubRepo->findByUser($user, $limit, $offset);
                $totalSubscriptions = $playlistSubRepo->countByUser($user);
                break;
            
            case 'users':
            default:
                $subscriptions = $this->subscriptionRepository->findBySubscriber($user, $limit, $offset);
                $totalSubscriptions = $this->subscriptionRepository->countBySubscriber($user);
                break;
        }

        $totalPages = ceil($totalSubscriptions / $limit);

        return $this->render('members/profile/subscriptions.html.twig', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'subscription_type' => $type,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_subscriptions' => $totalSubscriptions,
            'active_tab' => 'subscriptions'
        ]);
    }

    #[Route('/playlists', name: 'user_profile_playlists')]
    public function playlists(string $username): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkProfileAccess($user, 'playlists');

        $channels = $this->channelRepository->findByOwner($user);
        $allPlaylists = [];
        
        foreach ($channels as $channel) {
            $channelPlaylists = $this->playlistRepository->findBy(['channel' => $channel], ['createdAt' => 'DESC']);
            $allPlaylists = array_merge($allPlaylists, $channelPlaylists);
        }

        return $this->render('members/profile/playlists.html.twig', [
            'user' => $user,
            'playlists' => $allPlaylists,
            'channels' => $channels,
            'active_tab' => 'playlists'
        ]);
    }

    #[Route('/notifications', name: 'user_profile_notifications')]
    #[IsGranted('ROLE_USER')]
    public function notifications(string $username, Request $request): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkOwnerAccess($user);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $notifications = $this->notificationRepository->findByUser($user, $limit, $offset);

        return $this->render('members/profile/notifications.html.twig', [
            'user' => $user,
            'notifications' => $notifications,
            'current_page' => $page,
            'active_tab' => 'notifications'
        ]);
    }

    #[Route('/about', name: 'user_profile_about')]
    public function about(string $username): Response
    {
        $user = $this->findUserByUsername($username);

        return $this->render('members/profile/about.html.twig', [
            'user' => $user,
            'active_tab' => 'about'
        ]);
    }

    #[Route('/edit', name: 'user_profile_edit')]
    public function edit(string $username, Request $request): Response
    {
        $user = $this->findUserByUsername($username);
        $this->checkOwnerAccess($user);
        
        $form = $this->createForm(ProfileEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->addFlash('info', 'Форма отправлена');
            
            if ($form->isValid()) {
                $this->addFlash('info', 'Форма валидна');
            
            // Если страна была изменена, устанавливаем флаг countryManuallySet
            $originalCountry = $this->entityManager->getUnitOfWork()->getOriginalEntityData($user)['country'] ?? null;
            $newCountry = $user->getCountry();
            if ($originalCountry !== $newCountry && $newCountry !== null) {
                $user->setCountryManuallySet(true);
            }
            
            // Обработка аватара
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                try {
                    // Удаляем старый аватар
                    if ($user->getAvatar()) {
                        $this->imageService->deleteImage($user->getAvatar(), $this->getParameter('avatars_directory'));
                    }
                    
                    $newFilename = $this->imageService->processAvatar($avatarFile);
                    $user->setAvatar($newFilename);
                    $this->addFlash('success', 'Аватар успешно загружен: ' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Ошибка при загрузке аватара: ' . $e->getMessage());
                }
            }

            // Обработка обложки
            $coverImageFile = $form->get('coverImageFile')->getData();
            if ($coverImageFile) {
                try {
                    // Удаляем старую обложку
                    if ($user->getCoverImage()) {
                        $this->imageService->deleteImage($user->getCoverImage(), $this->getParameter('covers_directory'));
                    }
                    
                    $newFilename = $this->imageService->processCover($coverImageFile);
                    $user->setCoverImage($newFilename);
                    $this->addFlash('success', 'Обложка успешно загружена: ' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Ошибка при загрузке обложки: ' . $e->getMessage());
                }
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', 'Профиль успешно обновлен');
            return $this->redirectToRoute('user_profile_overview', ['username' => $user->getUsername()]);
            } else {
                $this->addFlash('error', 'Форма содержит ошибки');
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('members/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    private function findUserByUsername(string $username): User
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);
        
        if (!$user) {
            throw $this->createNotFoundException('Пользователь не найден');
        }

        return $user;
    }

    private function checkOwnerAccess(User $user): void
    {
        if ($this->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Доступ запрещен');
        }
    }

    private function checkProfileAccess(User $user, string $section): void
    {
        // Публичные разделы доступны всем
        $publicSections = ['videos', 'channels', 'series', 'subscriptions', 'playlists'];
        
        if (in_array($section, $publicSections)) {
            return;
        }

        // Приватные разделы только для владельца
        $this->checkOwnerAccess($user);
    }
}