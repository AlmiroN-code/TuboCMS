<?php

namespace App\Controller\Admin;

use App\Service\AdminNotifierService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notifications', name: 'admin_notifications_')]
#[IsGranted('ROLE_ADMIN')]
class AdminNotificationController extends AbstractController
{
    public function __construct(
        private NotifierInterface $notifier,
        private AdminNotifierService $adminNotifier,
        private SettingsService $settings,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $channels = [
            'email' => [
                'name' => 'Email',
                'icon' => '📧',
                'configured' => !empty($_ENV['MAILER_DSN'] ?? ''),
                'description' => 'Отправка уведомлений на email',
            ],
            'telegram' => [
                'name' => 'Telegram',
                'icon' => '📱',
                'configured' => !empty($_ENV['TELEGRAM_DSN'] ?? ''),
                'description' => 'Уведомления в Telegram чат/канал',
            ],
        ];

        $notificationTypes = [
            'new_video' => ['name' => 'Новое видео', 'channels' => ['email', 'telegram'], 'importance' => 'medium'],
            'video_error' => ['name' => 'Ошибка обработки видео', 'channels' => ['email', 'telegram'], 'importance' => 'high'],
            'new_user' => ['name' => 'Новый пользователь', 'channels' => ['email'], 'importance' => 'low'],
            'spam_comment' => ['name' => 'Спам-комментарий', 'channels' => ['email', 'telegram'], 'importance' => 'high'],
            'system_error' => ['name' => 'Системная ошибка', 'channels' => ['email', 'telegram'], 'importance' => 'urgent'],
            'storage_warning' => ['name' => 'Предупреждение о хранилище', 'channels' => ['email', 'telegram'], 'importance' => 'high'],
        ];

        return $this->render('admin/notifications/index.html.twig', [
            'channels' => $channels,
            'notification_types' => $notificationTypes,
            'admin_email' => $this->settings->get('admin_email', $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com'),
        ]);
    }

    #[Route('/test/{channel}', name: 'test', methods: ['POST'])]
    public function testChannel(Request $request, string $channel): Response
    {
        if (!$this->isCsrfTokenValid('notification_test', $request->request->get('_token'))) {
            $this->addFlash('error', 'Неверный CSRF токен');
            return $this->redirectToRoute('admin_notifications_index');
        }

        $adminEmail = $this->settings->get('admin_email', $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com');

        try {
            $channels = match ($channel) {
                'email' => ['email'],
                'telegram' => ['chat/telegram'],
                'all' => ['email', 'chat/telegram'],
                default => ['email'],
            };

            $notification = (new Notification('🧪 Тестовое уведомление', $channels))
                ->content(sprintf(
                    "Это тестовое уведомление от RexTube.\n\n" .
                    "📅 Время: %s\n" .
                    "👤 Отправитель: %s\n" .
                    "🔧 Канал: %s",
                    (new \DateTime())->format('d.m.Y H:i:s'),
                    $this->getUser()->getUserIdentifier(),
                    $channel
                ))
                ->importance(Notification::IMPORTANCE_LOW);

            $this->notifier->send($notification, new Recipient($adminEmail));
            
            $this->addFlash('success', "Тестовое уведомление отправлено через канал '$channel'");
        } catch (\Exception $e) {
            $this->addFlash('error', "Ошибка отправки: " . $e->getMessage());
        }

        return $this->redirectToRoute('admin_notifications_index');
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function sendCustom(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('notification_send', $request->request->get('_token'))) {
            $this->addFlash('error', 'Неверный CSRF токен');
            return $this->redirectToRoute('admin_notifications_index');
        }

        $title = $request->request->get('title', 'Уведомление');
        $message = $request->request->get('message', '');
        $channelsList = $request->request->all('channels') ?: ['email'];
        $importance = $request->request->get('importance', 'medium');
        $recipientEmail = $request->request->get('recipient', '');

        if (empty($message)) {
            $this->addFlash('error', 'Сообщение не может быть пустым');
            return $this->redirectToRoute('admin_notifications_index');
        }

        try {
            $channels = [];
            foreach ($channelsList as $ch) {
                $channels[] = $ch === 'telegram' ? 'chat/telegram' : $ch;
            }

            $importanceLevel = match ($importance) {
                'urgent' => Notification::IMPORTANCE_URGENT,
                'high' => Notification::IMPORTANCE_HIGH,
                'low' => Notification::IMPORTANCE_LOW,
                default => Notification::IMPORTANCE_MEDIUM,
            };

            $notification = (new Notification($title, $channels))
                ->content($message)
                ->importance($importanceLevel);

            $email = $recipientEmail ?: $this->settings->get('admin_email', $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com');
            $this->notifier->send($notification, new Recipient($email));

            $this->addFlash('success', 'Уведомление успешно отправлено');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка отправки: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_notifications_index');
    }
}
