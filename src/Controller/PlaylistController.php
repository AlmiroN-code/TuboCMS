<?php

namespace App\Controller;

use App\Entity\ChannelPlaylist;
use App\Entity\Video;
use App\Repository\ChannelPlaylistRepository;
use App\Repository\ChannelRepository;
use App\Repository\VideoRepository;
use App\Service\PlaylistService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PlaylistController extends AbstractController
{
    public function __construct(
        private ChannelPlaylistRepository $playlistRepository,
        private VideoRepository $videoRepository,
        private PlaylistService $playlistService,
        private SettingsService $settingsService,
        private ChannelRepository $channelRepository
    ) {}

    #[Route('/playlists', name: 'playlists_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $search = $request->query->get('search');
        
        if ($search) {
            $playlists = $this->playlistRepository->search($search, $this->getUser(), $limit, $offset);
            $totalPlaylists = count($playlists); // Приблизительный подсчет для поиска
        } else {
            $playlists = $this->playlistRepository->findPopular($this->getUser(), $limit, $offset);
            $totalPlaylists = count($playlists); // Приблизительный подсчет
        }

        $totalPages = ceil($totalPlaylists / $limit);

        return $this->render('playlist/index.html.twig', [
            'playlists' => $playlists,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_playlists' => $totalPlaylists,
            'search' => $search,
        ]);
    }

    #[Route('/playlist/{slug}', name: 'playlist_show')]
    public function show(string $slug, Request $request): Response
    {
        $playlist = $this->playlistRepository->findBySlug($slug, $this->getUser());
        
        if (!$playlist) {
            throw $this->createNotFoundException('Плейлист не найден');
        }

        // Проверяем права доступа
        if (!$this->playlistService->canUserViewPlaylist($playlist, $this->getUser())) {
            throw $this->createAccessDeniedException('У вас нет доступа к этому плейлисту');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $playlistVideos = $this->playlistService->getPlaylistVideos($playlist, $limit, $offset);
        $totalVideos = $playlist->getVideosCount();
        $totalPages = ceil($totalVideos / $limit);

        // Увеличиваем счетчик просмотров
        $this->playlistService->incrementPlaylistViews($playlist);

        return $this->render('playlist/show.html.twig', [
            'playlist' => $playlist,
            'playlist_videos' => $playlistVideos,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_videos' => $totalVideos,
        ]);
    }

    #[Route('/channel/{channelSlug}/playlists', name: 'channel_playlists')]
    public function channelPlaylists(string $channelSlug, Request $request): Response
    {
        $channel = $this->channelRepository->findBySlug($channelSlug);
        
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $playlists = $this->playlistService->getChannelPlaylists($channel, $this->getUser(), $limit, $offset);
        $totalPlaylists = $this->playlistRepository->countByChannel($channel, $this->getUser());
        $totalPages = ceil($totalPlaylists / $limit);

        return $this->render('playlist/channel_playlists.html.twig', [
            'channel' => $channel,
            'playlists' => $playlists,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_playlists' => $totalPlaylists,
        ]);
    }

    #[Route('/my-playlists', name: 'my_playlists')]
    #[IsGranted('ROLE_USER')]
    public function myPlaylists(Request $request): Response
    {
        $user = $this->getUser();
        $channels = $this->channelRepository->findByOwner($user);

        $allPlaylists = [];
        foreach ($channels as $channel) {
            $channelPlaylists = $this->playlistService->getChannelPlaylists($channel, $user);
            $allPlaylists = array_merge($allPlaylists, $channelPlaylists);
        }

        return $this->render('playlist/my_playlists.html.twig', [
            'playlists' => $allPlaylists,
            'channels' => $channels,
        ]);
    }

    #[Route('/playlist/create', name: 'playlist_create')]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        $user = $this->getUser();
        
        try {
            $channels = $this->channelRepository->findByOwner($user);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка загрузки каналов: ' . $e->getMessage());
            $channels = [];
        }

        if (empty($channels)) {
            $this->addFlash('warning', 'У вас нет каналов. Создайте канал сначала.');
            return $this->redirectToRoute('channel_create');
        }

        if ($request->isMethod('POST')) {
            // Проверка CSRF токена
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('playlist_create', $token)) {
                $this->addFlash('error', 'Недействительный CSRF токен');
                return $this->render('playlist/create.html.twig', ['channels' => $channels]);
            }
            
            $channelId = $request->request->getInt('channel_id');
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $visibility = $request->request->get('visibility', ChannelPlaylist::VISIBILITY_PUBLIC);

            if (!$title) {
                $this->addFlash('error', 'Название плейлиста обязательно');
                return $this->render('playlist/create.html.twig', ['channels' => $channels]);
            }

            $channel = null;
            foreach ($channels as $ch) {
                if ($ch->getId() === $channelId) {
                    $channel = $ch;
                    break;
                }
            }

            if (!$channel) {
                $this->addFlash('error', 'Выбранный канал не найден');
                return $this->render('playlist/create.html.twig', ['channels' => $channels]);
            }

            try {
                $playlist = $this->playlistService->createPlaylist($channel, $title, $description, $visibility);
                $this->addFlash('success', 'Плейлист успешно создан');
                return $this->redirectToRoute('playlist_show', ['slug' => $playlist->getSlug()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при создании плейлиста: ' . $e->getMessage());
            }
        }

        return $this->render('playlist/create.html.twig', [
            'channels' => $channels,
        ]);
    }

    #[Route('/playlist/{slug}/edit', name: 'playlist_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(string $slug, Request $request): Response
    {
        $playlist = $this->playlistRepository->findBySlug($slug, $this->getUser());
        
        if (!$playlist) {
            throw $this->createNotFoundException('Плейлист не найден');
        }

        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            throw $this->createAccessDeniedException('У вас нет прав для редактирования этого плейлиста');
        }

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $visibility = $request->request->get('visibility');

            if (!$title) {
                $this->addFlash('error', 'Название плейлиста обязательно');
            } else {
                try {
                    $this->playlistService->updatePlaylist($playlist, $title, $description, $visibility);
                    $this->addFlash('success', 'Плейлист успешно обновлен');
                    return $this->redirectToRoute('playlist_show', ['slug' => $playlist->getSlug()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Ошибка при обновлении плейлиста: ' . $e->getMessage());
                }
            }
        }

        return $this->render('playlist/edit.html.twig', [
            'playlist' => $playlist,
        ]);
    }

    #[Route('/playlist/{slug}/add-video', name: 'playlist_add_video', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addVideo(string $slug, Request $request): JsonResponse
    {
        $playlist = $this->playlistRepository->findBySlug($slug, $this->getUser());
        
        if (!$playlist) {
            return new JsonResponse(['error' => 'Плейлист не найден'], 404);
        }

        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return new JsonResponse(['error' => 'У вас нет прав для управления этим плейлистом'], 403);
        }

        $videoId = $request->request->getInt('video_id');
        $video = $this->videoRepository->find($videoId);

        if (!$video) {
            return new JsonResponse(['error' => 'Видео не найдено'], 404);
        }

        try {
            $this->playlistService->addVideoToPlaylist($playlist, $video, $this->getUser());
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Видео добавлено в плейлист',
                'videosCount' => $playlist->getVideosCount()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/playlist/{slug}/remove-video', name: 'playlist_remove_video', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function removeVideo(string $slug, Request $request): JsonResponse
    {
        $playlist = $this->playlistRepository->findBySlug($slug, $this->getUser());
        
        if (!$playlist) {
            return new JsonResponse(['error' => 'Плейлист не найден'], 404);
        }

        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return new JsonResponse(['error' => 'У вас нет прав для управления этим плейлистом'], 403);
        }

        $videoId = $request->request->getInt('video_id');
        $video = $this->videoRepository->find($videoId);

        if (!$video) {
            return new JsonResponse(['error' => 'Видео не найдено'], 404);
        }

        try {
            $this->playlistService->removeVideoFromPlaylist($playlist, $video);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Видео удалено из плейлиста',
                'videosCount' => $playlist->getVideosCount()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/playlist/{slug}/move-video', name: 'playlist_move_video', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function moveVideo(string $slug, Request $request): JsonResponse
    {
        $playlist = $this->playlistRepository->findBySlug($slug, $this->getUser());
        
        if (!$playlist) {
            return new JsonResponse(['error' => 'Плейлист не найден'], 404);
        }

        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return new JsonResponse(['error' => 'У вас нет прав для управления этим плейлистом'], 403);
        }

        $videoId = $request->request->getInt('video_id');
        $newPosition = $request->request->getInt('new_position');
        
        $video = $this->videoRepository->find($videoId);

        if (!$video) {
            return new JsonResponse(['error' => 'Видео не найдено'], 404);
        }

        try {
            $this->playlistService->moveVideoInPlaylist($playlist, $video, $newPosition);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Позиция видео изменена'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/playlist/{slug}/delete', name: 'playlist_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $slug): Response
    {
        $playlist = $this->playlistRepository->findBySlug($slug, $this->getUser());
        
        if (!$playlist) {
            throw $this->createNotFoundException('Плейлист не найден');
        }

        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            throw $this->createAccessDeniedException('У вас нет прав для удаления этого плейлиста');
        }

        $channelSlug = $playlist->getChannel()->getSlug();
        
        $this->playlistService->deletePlaylist($playlist);
        
        $this->addFlash('success', 'Плейлист успешно удален');
        return $this->redirectToRoute('channel_playlists', ['channelSlug' => $channelSlug]);
    }
}