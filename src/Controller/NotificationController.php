<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService,
        private NotificationRepository $notificationRepository,
    ) {
    }

    #[Route('', name: 'app_notification_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $user = $this->getUser();
        $notifications = $this->notificationService->getNotifications($user, $page, $limit);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'currentPage' => $page,
        ]);
    }

    #[Route('/{id}/read', name: 'app_notification_mark_read', methods: ['POST'])]
    public function markRead(Notification $notification): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $this->notificationService->markAsRead($notification);

        if ($this->isJsonRequest()) {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('app_notification_index');
    }

    #[Route('/read-all', name: 'app_notification_mark_all_read', methods: ['POST'])]
    public function markAllRead(): Response
    {
        $this->notificationService->markAllAsRead($this->getUser());

        if ($this->isJsonRequest()) {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('app_notification_index');
    }

    #[Route('/badge', name: 'app_notification_badge', methods: ['GET'])]
    public function badge(): Response
    {
        $count = $this->notificationService->getUnreadCount($this->getUser());

        return $this->render('partials/_notifications_badge.html.twig', [
            'unreadCount' => $count,
        ]);
    }

    private function isJsonRequest(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
