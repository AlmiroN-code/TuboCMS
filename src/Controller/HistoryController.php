<?php

namespace App\Controller;

use App\Repository\VideoRepository;
use App\Service\SettingsService;
use App\Service\WatchHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/history')]
#[IsGranted('ROLE_USER')]
class HistoryController extends AbstractController
{
    public function __construct(
        private WatchHistoryService $watchHistoryService,
        private VideoRepository $videoRepository,
        private SettingsService $settingsService
    ) {
    }

    #[Route('', name: 'app_history_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Redirect to new profile history URL
        return $this->redirectToRoute('user_profile_history', [
            'username' => $this->getUser()->getUsername()
        ]);
    }

    #[Route('/record', name: 'app_history_record', methods: ['POST'])]
    public function record(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $videoId = $data['videoId'] ?? null;
        $seconds = $data['seconds'] ?? 0;

        if (!$videoId) {
            return new JsonResponse(['error' => 'Video ID required'], 400);
        }

        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            return new JsonResponse(['error' => 'Video not found'], 404);
        }

        $this->watchHistoryService->record($this->getUser(), $video, (int) $seconds);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{videoId}', name: 'app_history_delete', methods: ['DELETE'])]
    public function delete(int $videoId): Response
    {
        $video = $this->videoRepository->find($videoId);
        if ($video) {
            $this->watchHistoryService->deleteEntry($this->getUser(), $video);
        }

        if ($this->isJsonRequest()) {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('user_profile_history', [
            'username' => $this->getUser()->getUsername()
        ]);
    }

    #[Route('', name: 'app_history_clear', methods: ['DELETE'])]
    public function clear(): Response
    {
        $this->watchHistoryService->clearHistory($this->getUser());

        if ($this->isJsonRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'История очищена');
        return $this->redirectToRoute('user_profile_history', [
            'username' => $this->getUser()->getUsername()
        ]);
    }

    private function isJsonRequest(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
