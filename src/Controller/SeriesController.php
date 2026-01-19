<?php

namespace App\Controller;

use App\Entity\Series;
use App\Repository\SeriesRepository;
use App\Repository\VideoRepository;
use App\Service\SeriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/series')]
class SeriesController extends AbstractController
{
    public function __construct(
        private SeriesService $seriesService,
        private SeriesRepository $seriesRepository,
        private VideoRepository $videoRepository,
    ) {
    }

    #[Route('', name: 'app_series_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        $series = $this->seriesRepository->findByAuthor($user);

        return $this->render('series/index.html.twig', [
            'series' => $series,
        ]);
    }

    #[Route('/{slug}', name: 'app_series_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $series = $this->seriesRepository->findBySlug($slug);
        if (!$series) {
            throw $this->createNotFoundException();
        }

        $isOwner = $this->getUser() === $series->getAuthor();
        $userVideos = [];
        
        if ($isOwner) {
            // Get user's videos that are not already in any season
            $userVideos = $this->videoRepository->findBy(
                ['createdBy' => $this->getUser(), 'status' => 'published'],
                ['createdAt' => 'DESC']
            );
        }

        return $this->render('series/show.html.twig', [
            'series' => $series,
            'isOwner' => $isOwner,
            'user_videos' => $userVideos,
        ]);
    }

    #[Route('/create', name: 'app_series_create', methods: ['GET', 'POST'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $description = $request->request->get('description');

            if (empty($title)) {
                $this->addFlash('error', 'Название серии обязательно');
                return $this->redirectToRoute('app_series_create');
            }

            $series = $this->seriesService->create($this->getUser(), $title, $description);

            $this->addFlash('success', 'Серия создана');
            return $this->redirectToRoute('app_series_show', ['slug' => $series->getSlug()]);
        }

        return $this->render('series/create.html.twig');
    }

    #[Route('/{id}/seasons', name: 'app_series_add_season', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addSeason(Request $request, Series $series): Response
    {
        if ($series->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $title = $request->request->get('title');
        $this->seriesService->addSeason($series, $title);

        $this->addFlash('success', 'Сезон добавлен');
        return $this->redirectToRoute('app_series_show', ['slug' => $series->getSlug()]);
    }

    #[Route('/{id}/episodes', name: 'app_series_add_episode', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addEpisode(Request $request, Series $series): Response
    {
        if ($series->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $seasonId = $request->request->getInt('season_id');
        $videoId = $request->request->getInt('video_id');

        $season = null;
        foreach ($series->getSeasons() as $s) {
            if ($s->getId() === $seasonId) {
                $season = $s;
                break;
            }
        }

        if (!$season) {
            $this->addFlash('error', 'Сезон не найден');
            return $this->redirectToRoute('app_series_show', ['slug' => $series->getSlug()]);
        }

        $video = $this->videoRepository->find($videoId);
        if (!$video || $video->getCreatedBy() !== $this->getUser()) {
            $this->addFlash('error', 'Видео не найдено');
            return $this->redirectToRoute('app_series_show', ['slug' => $series->getSlug()]);
        }

        $this->seriesService->addEpisode($season, $video);

        $this->addFlash('success', 'Эпизод добавлен');
        return $this->redirectToRoute('app_series_show', ['slug' => $series->getSlug()]);
    }
}
