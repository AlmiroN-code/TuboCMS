<?php

namespace App\Controller;

use App\Entity\Storage;
use App\Repository\VideoRepository;
use App\Service\StorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

#[Route('/videos')]
class VideoController extends AbstractController
{
    #[Route('/', name: 'app_videos')]
    public function list(
        Request $request,
        VideoRepository $videoRepository
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $videos = $videoRepository->findPublished($limit, $offset);

        return $this->render('video/list.html.twig', [
            'videos' => $videos,
            'page' => $page,
            'sort' => 'newest',
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
            'title' => 'Популярные видео',
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
            'title' => 'В тренде',
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
            'title' => 'Новые видео',
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

        $videos = [];
        $totalResults = 0;

        if ($query && strlen($query) >= 2) {
            $videos = $videoRepository->searchVideos($query, $limit, $offset);
            $totalResults = $videoRepository->getSearchCount($query);
            
            $logger->info('Video search performed', [
                'query' => $query,
                'results' => count($videos),
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
        ]);
    }

    #[Route('/{slug}', name: 'video_detail')]
    public function detail(
        string $slug,
        VideoRepository $videoRepository,
        EntityManagerInterface $em,
        StorageManager $storageManager
    ): Response
    {
        $video = $videoRepository->findOneBy(['slug' => $slug]);

        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        // Increment views
        $video->incrementViews();
        $em->flush();

        // Get related videos
        $relatedVideos = [];
        if ($video->getCategory()) {
            $relatedVideos = $videoRepository->findByCategory(
                $video->getCategory()->getId(),
                12
            );
        }

        // Get user's like status and subscription status
        $userLike = null;
        $isSubscribed = false;
        if ($this->getUser()) {
            $likeRepo = $em->getRepository(\App\Entity\VideoLike::class);
            $userLike = $likeRepo->findByUserAndVideo($this->getUser(), $video);
            
            if ($video->getCreatedBy()) {
                $subRepo = $em->getRepository(\App\Entity\Subscription::class);
                $isSubscribed = $subRepo->findOneBy([
                    'subscriber' => $this->getUser(),
                    'channel' => $video->getCreatedBy()
                ]) !== null;
            }
        }

        // Build video file URLs based on storage type
        // Requirement 3.1: Generate appropriate URL based on storage type
        // Requirement 3.2: Serve FTP/SFTP files through proxy
        // Requirement 3.3: Return direct URL for HTTP storage
        $videoFileUrls = $this->buildVideoFileUrls($video, $storageManager);

        return $this->render('video/detail.html.twig', [
            'video' => $video,
            'related_videos' => $relatedVideos,
            'user_like' => $userLike,
            'is_subscribed' => $isSubscribed,
            'video_file_urls' => $videoFileUrls,
        ]);
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
}
