<?php

namespace App\Controller;

use App\Repository\VideoRepository;
use App\Repository\WatchLaterRepository;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/watch-later')]
#[IsGranted('ROLE_USER')]
class WatchLaterController extends AbstractController
{
    public function __construct(
        private readonly WatchLaterRepository $watchLaterRepository,
        private readonly VideoRepository $videoRepository,
        private readonly SettingsService $settingsService
    ) {
    }

    #[Route('', name: 'watch_later_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        // Перенаправляем на новую систему профилей с вкладками
        return $this->redirectToRoute('user_profile_watch_later', ['username' => $this->getUser()->getUsername()]);
    }

    #[Route('/toggle/{id}', name: 'watch_later_toggle', methods: ['POST'])]
    public function toggle(int $id, TranslatorInterface $translator): JsonResponse
    {
        $user = $this->getUser();
        $video = $this->videoRepository->find($id);

        if (!$video) {
            return $this->json([
                'success' => false,
                'message' => $translator->trans('video.not_found', [], 'messages')
            ], 404);
        }

        $isInWatchLater = $this->watchLaterRepository->isInWatchLater($user, $video);

        try {
            if ($isInWatchLater) {
                $this->watchLaterRepository->removeFromWatchLater($user, $video);
                $message = $translator->trans('toast.watch_later_removed', [], 'messages');
                $added = false;
            } else {
                $this->watchLaterRepository->addToWatchLater($user, $video);
                $message = $translator->trans('toast.watch_later_added', [], 'messages');
                $added = true;
            }

            return $this->json([
                'success' => true,
                'message' => $message,
                'added' => $added,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $translator->trans('toast.error_generic', [], 'messages'),
            ], 500);
        }
    }

    #[Route('/check/{id}', name: 'watch_later_check', methods: ['GET'])]
    public function check(int $id): JsonResponse
    {
        $user = $this->getUser();
        $video = $this->videoRepository->find($id);

        if (!$video) {
            return $this->json(['inWatchLater' => false], 404);
        }

        $isInWatchLater = $this->watchLaterRepository->isInWatchLater($user, $video);

        return $this->json(['inWatchLater' => $isInWatchLater]);
    }
}
