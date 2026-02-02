<?php

namespace App\Controller;

use App\Entity\Video;
use App\Service\BookmarkService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/bookmarks')]
#[IsGranted('ROLE_USER')]
class BookmarkController extends AbstractController
{
    public function __construct(
        private BookmarkService $bookmarkService,
        private TranslatorInterface $translator,
        private SettingsService $settingsService
    ) {
    }

    #[Route('', name: 'app_bookmark_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Перенаправляем на новую систему профилей с вкладками
        return $this->redirectToRoute('user_profile_bookmarks', ['username' => $this->getUser()->getUsername()]);
    }

    #[Route('/video/{id}', name: 'app_bookmark_toggle', methods: ['POST'])]
    public function toggle(Video $video): Response
    {
        $user = $this->getUser();
        $isBookmarked = $this->bookmarkService->toggle($user, $video);

        if ($this->isJsonRequest()) {
            $message = $isBookmarked 
                ? $this->translator->trans('toast.bookmark_added', [], 'messages')
                : $this->translator->trans('toast.bookmark_removed', [], 'messages');

            return new JsonResponse([
                'success' => true,
                'isBookmarked' => $isBookmarked,
                'message' => $message,
            ]);
        }

        return $this->render('video/_bookmark_button.html.twig', [
            'video' => $video,
            'isBookmarked' => $isBookmarked,
        ]);
    }

    private function isJsonRequest(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
