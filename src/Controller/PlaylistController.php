<?php

namespace App\Controller;

use App\Entity\Playlist;
use App\Entity\Video;
use App\Repository\PlaylistRepository;
use App\Repository\VideoRepository;
use App\Service\PlaylistService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/playlists')]
class PlaylistController extends AbstractController
{
    public function __construct(
        private PlaylistService $playlistService,
        private PlaylistRepository $playlistRepository,
        private VideoRepository $videoRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_playlist_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $playlists = $this->playlistService->getUserPlaylists($user);

        // Return JSON for AJAX requests
        if ($request->query->get('format') === 'json' || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            $data = array_map(fn($p) => [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'videosCount' => $p->getVideosCount(),
            ], $playlists);
            return $this->json($data);
        }

        return $this->render('playlist/index.html.twig', [
            'playlists' => $playlists,
        ]);
    }

    #[Route('/create', name: 'app_playlist_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $description = $request->request->get('description');
            $isPublic = $request->request->getBoolean('is_public', true);

            if (empty($title)) {
                $this->addFlash('error', 'Название плейлиста обязательно');
                return $this->redirectToRoute('app_playlist_create');
            }

            $playlist = $this->playlistService->create(
                $this->getUser(),
                $title,
                $description,
                $isPublic
            );

            $this->addFlash('success', 'Плейлист создан');
            return $this->redirectToRoute('app_playlist_show', ['id' => $playlist->getId()]);
        }

        return $this->render('playlist/create.html.twig');
    }

    #[Route('/{id}', name: 'app_playlist_show', methods: ['GET'])]
    public function show(Playlist $playlist): Response
    {
        $user = $this->getUser();
        
        // Check access
        if (!$playlist->isPublic() && $playlist->getOwner() !== $user) {
            throw $this->createNotFoundException();
        }

        return $this->render('playlist/show.html.twig', [
            'playlist' => $playlist,
            'isOwner' => $user === $playlist->getOwner(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_playlist_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Playlist $playlist): Response
    {
        if ($playlist->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $title = trim($request->request->get('title', ''));
            $description = $request->request->get('description');
            $isPublic = $request->request->getBoolean('is_public', true);

            if (empty($title)) {
                $this->addFlash('error', 'Название плейлиста обязательно');
                return $this->redirectToRoute('app_playlist_edit', ['id' => $playlist->getId()]);
            }

            $this->playlistService->update($playlist, $title, $description, $isPublic);

            $this->addFlash('success', 'Плейлист обновлён');
            return $this->redirectToRoute('app_playlist_show', ['id' => $playlist->getId()]);
        }

        return $this->render('playlist/edit.html.twig', [
            'playlist' => $playlist,
        ]);
    }

    #[Route('/{id}', name: 'app_playlist_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Playlist $playlist): Response
    {
        if ($playlist->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $this->playlistService->delete($playlist);

        if ($this->isJsonRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Плейлист удалён');
        return $this->redirectToRoute('app_playlist_index');
    }

    #[Route('/{id}/videos/{videoId}', name: 'app_playlist_add_video', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addVideo(Playlist $playlist, int $videoId): Response
    {
        if ($playlist->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        $this->playlistService->addVideo($playlist, $video);

        if ($this->isJsonRequest()) {
            return new JsonResponse([
                'success' => true,
                'videosCount' => $playlist->getVideosCount(),
                'message' => $this->translator->trans('toast.playlist_added', [], 'messages'),
            ]);
        }

        return $this->redirectToRoute('app_playlist_show', ['id' => $playlist->getId()]);
    }

    #[Route('/{id}/videos/{videoId}', name: 'app_playlist_remove_video', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeVideo(Playlist $playlist, int $videoId): Response
    {
        if ($playlist->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $video = $this->videoRepository->find($videoId);
        if ($video) {
            $this->playlistService->removeVideo($playlist, $video);
        }

        if ($this->isJsonRequest()) {
            return new JsonResponse([
                'success' => true,
                'videosCount' => $playlist->getVideosCount(),
                'message' => $this->translator->trans('toast.playlist_removed', [], 'messages'),
            ]);
        }

        return $this->redirectToRoute('app_playlist_show', ['id' => $playlist->getId()]);
    }

    #[Route('/{id}/reorder', name: 'app_playlist_reorder', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function reorder(Request $request, Playlist $playlist): JsonResponse
    {
        if ($playlist->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $data = json_decode($request->getContent(), true);
        $videoIds = $data['videoIds'] ?? [];

        $this->playlistService->reorderVideos($playlist, $videoIds);

        return new JsonResponse(['success' => true]);
    }

    private function isJsonRequest(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
