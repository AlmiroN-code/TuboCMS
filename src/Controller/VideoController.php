<?php

namespace App\Controller;

use App\Entity\Storage;
use App\Repository\VideoRepository;
use App\Service\ImpressionTracker;
use App\Service\RecommendationService;
use App\Service\SeeAlsoService;
use App\Service\StorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/videos')]
class VideoController extends AbstractController
{
    #[Route('/track-impressions', name: 'video_track_impressions', methods: ['POST'])]
    public function trackImpressions(
        Request $request,
        ImpressionTracker $impressionTracker
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $videoIds = $data['video_ids'] ?? [];
        
        if (!is_array($videoIds) || empty($videoIds)) {
            return new JsonResponse(['success' => false], 400);
        }
        
        // Фильтруем только числовые ID
        $videoIds = array_filter(array_map('intval', $videoIds), fn($id) => $id > 0);
        
        if (!empty($videoIds)) {
            $impressionTracker->trackImpressions($videoIds);
        }
        
        return new JsonResponse(['success' => true, 'tracked' => count($videoIds)]);
    }

    #[Route('/', name: 'app_videos')]
    public function list(
        Request $request,
        VideoRepository $videoRepository
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        // Build filters from request
        $filters = [
            'duration' => $request->query->get('duration'),
            'sort' => $request->query->get('sort', 'newest'),
            'category' => $request->query->getInt('category') ?: null,
            'tag' => $request->query->getInt('tag') ?: null,
        ];

        // Remove null values
        $filters = array_filter($filters, fn($v) => $v !== null);

        $videos = $videoRepository->findWithFilters($filters, $limit, $offset);
        $total = $videoRepository->countWithFilters($filters);

        return $this->render('video/list.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'sort' => $filters['sort'] ?? 'newest',
            'duration' => $filters['duration'] ?? null,
            'total_pages' => ceil($total / $limit),
            'filters' => $filters,
        ]);
    }

    #[Route('/popular', name: 'app_videos_popular')]
    public function popular(
        Request $request,
        VideoRepository $videoRepository
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $videos = $videoRepository->findPopularPaginated($limit, $offset);
        $total = $videoRepository->countPublished();

        return $this->render('video/list.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'sort' => 'popular',
            'title' => 'video.popular_videos',
            'total_pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/trending', name: 'app_videos_trending')]
    public function trending(
        Request $request,
        VideoRepository $videoRepository
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $videos = $videoRepository->findTrending($limit, $offset);
        $total = $videoRepository->countPublished();

        return $this->render('video/list.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'sort' => 'trending',
            'title' => 'video.trending_videos',
            'total_pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/newest', name: 'app_videos_newest')]
    public function newest(
        Request $request,
        VideoRepository $videoRepository
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $videos = $videoRepository->findPublished($limit, $offset);
        $total = $videoRepository->countPublished();

        return $this->render('video/list.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'sort' => 'newest',
            'title' => 'video.newest_videos',
            'total_pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/load-more/{sort}', name: 'app_videos_load_more', methods: ['GET'])]
    public function loadMore(
        Request $request,
        VideoRepository $videoRepository,
        string $sort = 'newest'
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $videos = match($sort) {
            'popular' => $videoRepository->findPopularPaginated($limit, $offset),
            'trending' => $videoRepository->findTrending($limit, $offset),
            default => $videoRepository->findPublished($limit, $offset),
        };

        $total = $videoRepository->countPublished();
        $hasMore = ($page * $limit) < $total;

        return $this->render('video/_grid_items.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'sort' => $sort,
            'has_more' => $hasMore,
        ]);
    }

    #[Route('/search/suggestions', name: 'video_search_suggestions', methods: ['GET'])]
    public function searchSuggestions(
        Request $request,
        VideoRepository $videoRepository
    ): Response {
        $query = trim($request->query->get('q', ''));
        
        if (strlen($query) < 2) {
            return new Response('');
        }

        $videos = $videoRepository->searchVideos($query, 5, 0);

        return $this->render('video/_search_suggestions.html.twig', [
            'videos' => $videos,
            'query' => $query,
        ]);
    }

    #[Route('/search', name: 'video_search', methods: ['GET'])]
    public function search(
        Request $request,
        VideoRepository $videoRepository,
        RateLimiterFactory $searchLimiter,
        LoggerInterface $logger
    ): Response
    {
        // Apply rate limiting for search
        $limiter = $searchLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            $logger->warning('Search rate limit exceeded', [
                'ip' => $request->getClientIp(),
                'query' => $request->query->get('q', '')
            ]);
            $this->addFlash('error', 'Слишком много поисковых запросов. Попробуйте позже.');
            return $this->redirectToRoute('app_videos');
        }

        $query = trim($request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        // Собираем фильтры
        $filters = [
            'duration' => $request->query->get('duration'),
            'category' => $request->query->getInt('category') ?: null,
            'tag' => $request->query->getInt('tag') ?: null,
        ];
        $filters = array_filter($filters, fn($v) => $v !== null);

        $videos = [];
        $totalResults = 0;

        if ($query && strlen($query) >= 2) {
            $videos = $videoRepository->searchVideos($query, $limit, $offset, $filters);
            $totalResults = $videoRepository->getSearchCount($query, $filters);
            
            $logger->info('Video search performed', [
                'query' => $query,
                'results' => count($videos),
                'filters' => $filters,
                'ip' => $request->getClientIp()
            ]);
        } elseif ($query && strlen($query) < 2) {
            $this->addFlash('info', 'Поисковый запрос должен содержать минимум 2 символа.');
        }

        return $this->render('video/search.html.twig', [
            'videos' => $videos,
            'query' => $query,
            'page' => $page,
            'total_results' => $totalResults,
            'total_pages' => $totalResults > 0 ? ceil($totalResults / $limit) : 0,
            'filters' => $filters,
        ]);
    }

    #[Route('/{slug}', name: 'video_detail', requirements: ['slug' => '^(?!upload$).+'])]
    public function detail(
        string $slug,
        VideoRepository $videoRepository,
        EntityManagerInterface $em,
        StorageManager $storageManager,
        RecommendationService $recommendationService,
        SeeAlsoService $seeAlsoService,
        CacheInterface $cache
    ): Response
    {
        // Оптимизированный запрос с JOIN для загрузки связанных данных
        $video = $videoRepository->findOneWithRelations($slug);

        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        // Increment views (асинхронно, чтобы не блокировать ответ)
        $video->incrementViews();
        $em->flush();

        // Кешируем связанные видео
        $relatedVideos = $cache->get(
            "related_videos_{$video->getId()}",
            function() use ($recommendationService, $video) {
                return $recommendationService->getRelatedVideos($video, 12);
            },
            300 // 5 минут
        );

        // Get user's like status, subscription status, bookmark status and playlists
        $userLike = null;
        $isSubscribed = false;
        $isBookmarked = false;
        $userPlaylists = [];
        
        $user = $this->getUser();
        if ($user) {
            // Один оптимизированный запрос для всех данных пользователя
            $userData = $this->getUserVideoData($em, $user, $video);
            $userLike = $userData['like'];
            $isSubscribed = $userData['subscribed'];
            $isBookmarked = $userData['bookmarked'];
            $userPlaylists = $userData['playlists'];
        }

        // Build video file URLs based on storage type
        $videoFileUrls = $this->buildVideoFileUrls($video, $storageManager);

        // Блок "Смотрите также" с кешированием
        $seeAlso = $cache->get(
            "see_also_{$video->getId()}",
            function() use ($seeAlsoService, $video) {
                $seeAlso = [
                    'similar_videos' => $seeAlsoService->getVideosWithSimilarTags($video->getTags()->toArray(), $video, 6),
                ];

                // Добавляем видео моделей, если есть исполнители
                if ($video->getPerformers()->count() > 0) {
                    $seeAlso['model_videos'] = [];
                    foreach ($video->getPerformers()->slice(0, 2) as $performer) {
                        $modelVideos = $seeAlsoService->getOtherVideosForModel($performer, $video, 3);
                        if (!empty($modelVideos)) {
                            $seeAlso['model_videos'][$performer->getDisplayName()] = $modelVideos;
                        }
                    }
                }

                return $seeAlso;
            },
            300 // 5 минут
        );

        // Добавляем HTTP кеширование
        $response = $this->render('video/detail.html.twig', [
            'video' => $video,
            'related_videos' => $relatedVideos,
            'user_like' => $userLike,
            'is_subscribed' => $isSubscribed,
            'is_bookmarked' => $isBookmarked,
            'user_playlists' => $userPlaylists,
            'video_file_urls' => $videoFileUrls,
            'see_also' => $seeAlso,
        ]);

        // Кешируем ответ на 5 минут для анонимных пользователей
        if (!$user) {
            $response->setSharedMaxAge(300);
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        return $response;
    }

    /**
     * Получение данных пользователя для видео одним запросом
     */
    private function getUserVideoData(EntityManagerInterface $em, $user, $video): array
    {
        $result = [
            'like' => null,
            'subscribed' => false,
            'bookmarked' => false,
            'playlists' => [],
        ];

        // Like
        $likeRepo = $em->getRepository(\App\Entity\VideoLike::class);
        $result['like'] = $likeRepo->findByUserAndVideo($user, $video);

        // Subscription (только если есть автор)
        if ($video->getCreatedBy() && $video->getCreatedBy()->getId() !== $user->getId()) {
            $subRepo = $em->getRepository(\App\Entity\Subscription::class);
            $result['subscribed'] = $subRepo->findOneBy([
                'subscriber' => $user,
                'channel' => $video->getCreatedBy()
            ]) !== null;
        }

        // Bookmark
        $bookmarkRepo = $em->getRepository(\App\Entity\Bookmark::class);
        $result['bookmarked'] = $bookmarkRepo->findOneBy([
            'user' => $user,
            'video' => $video
        ]) !== null;

        // Playlists
        $playlistRepo = $em->getRepository(\App\Entity\Playlist::class);
        $result['playlists'] = $playlistRepo->findBy(['owner' => $user], ['createdAt' => 'DESC']);

        return $result;
    }

    /**
     * Build URLs for video files based on storage type.
     * 
     * Requirement 3.1: WHEN a user requests a video THEN the System SHALL 
     * generate appropriate URL based on storage type
     * 
     * Requirement 3.2: WHEN video is stored on FTP/SFTP THEN the System SHALL 
     * serve the file through a proxy endpoint
     * 
     * Requirement 3.3: WHEN video is stored on Remote Server THEN the System 
     * SHALL return the direct URL to the remote file
     * 
     * @return array<int, string> Map of VideoFile ID to URL
     */
    private function buildVideoFileUrls($video, StorageManager $storageManager): array
    {
        $urls = [];
        
        foreach ($video->getEncodedFiles() as $videoFile) {
            $storage = $videoFile->getStorage();
            $remotePath = $videoFile->getRemotePath();
            
            // Local file - use local path
            if ($storage === null || $remotePath === null) {
                $localPath = $videoFile->getFile();
                $urls[$videoFile->getId()] = $localPath ? '/media/' . $localPath : '';
                continue;
            }
            
            // Remote storage - generate appropriate URL based on type
            $storageType = $storage->getType();
            
            if ($storageType === Storage::TYPE_HTTP) {
                // HTTP storage - direct URL
                $urls[$videoFile->getId()] = $storageManager->getFileUrl($videoFile);
            } else {
                // FTP/SFTP storage - proxy URL
                $urls[$videoFile->getId()] = $this->generateUrl(
                    'storage_file',
                    ['id' => $videoFile->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
            }
        }
        
        return $urls;
    }

    #[Route('/{slug}/embed', name: 'video_embed', requirements: ['slug' => '^(?!upload$).+'])]
    public function embed(
        string $slug,
        VideoRepository $videoRepository
    ): Response {
        $video = $videoRepository->findOneBy(['slug' => $slug, 'status' => 'published']);
        
        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        return $this->render('video/embed.html.twig', [
            'video' => $video,
        ]);
    }

    #[Route('/{slug}/oembed', name: 'video_oembed', requirements: ['slug' => '^(?!upload$).+'])]
    public function oEmbed(
        string $slug,
        VideoRepository $videoRepository,
        \App\Service\EmbedService $embedService
    ): JsonResponse {
        $video = $videoRepository->findOneBy(['slug' => $slug, 'status' => 'published']);
        
        if (!$video) {
            return new JsonResponse(['error' => 'Video not found'], 404);
        }

        $maxWidth = (int) $_GET['maxwidth'] ?? 640;
        $maxHeight = (int) $_GET['maxheight'] ?? 360;

        // Ограничиваем размеры
        $maxWidth = min($maxWidth, 1280);
        $maxHeight = min($maxHeight, 720);

        $oEmbed = $embedService->generateOEmbed($video, $maxWidth, $maxHeight);

        return new JsonResponse($oEmbed);
    }
}
