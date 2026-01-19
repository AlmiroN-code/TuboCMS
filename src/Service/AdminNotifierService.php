<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\User;
use App\Entity\Video;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * Сервис для отправки уведомлений администраторам.
 */
class AdminNotifierService
{
    public function __construct(
        private NotifierInterface $notifier,
        private SettingsService $settings,
    ) {
    }

    /**
     * Уведомление о новом видео.
     */
    public function notifyNewVideo(Video $video): void
    {
        $notification = (new Notification('🎬 Новое видео загружено', ['email', 'chat/telegram']))
            ->content(sprintf(
                "Пользователь %s загрузил новое видео:\n\n📹 %s\n📁 Категория: %s\n⏱ Длительность: %s",
                $video->getCreatedBy()?->getUsername() ?? 'Аноним',
                $video->getTitle(),
                $video->getCategory()?->getName() ?? 'Без категории',
                $video->getDurationFormatted()
            ))
            ->importance(Notification::IMPORTANCE_MEDIUM);

        $this->sendToAdmins($notification);
    }

    /**
     * Уведомление об ошибке обработки видео.
     */
    public function notifyVideoProcessingError(Video $video, string $error): void
    {
        $notification = (new Notification('❌ Ошибка обработки видео', ['email', 'chat/telegram']))
            ->content(sprintf(
                "Ошибка при обработке видео:\n\n📹 %s (ID: %d)\n👤 Автор: %s\n\n❗ Ошибка: %s",
                $video->getTitle(),
                $video->getId(),
                $video->getCreatedBy()?->getUsername() ?? 'Аноним',
                $error
            ))
            ->importance(Notification::IMPORTANCE_HIGH);

        $this->sendToAdmins($notification);
    }

    /**
     * Уведомление о новом пользователе.
     */
    public function notifyNewUser(User $user): void
    {
        $notification = (new Notification('👤 Новый пользователь', ['email']))
            ->content(sprintf(
                "Зарегистрирован новый пользователь:\n\n👤 %s\n📧 %s\n📅 %s",
                $user->getUsername(),
                $user->getEmail(),
                $user->getCreatedAt()->format('d.m.Y H:i')
            ))
            ->importance(Notification::IMPORTANCE_LOW);

        $this->sendToAdmins($notification);
    }

    /**
     * Уведомление о подозрительном комментарии (спам).
     */
    public function notifySpamComment(Comment $comment): void
    {
        $notification = (new Notification('🚨 Подозрительный комментарий', ['email', 'chat/telegram']))
            ->content(sprintf(
                "Обнаружен подозрительный комментарий:\n\n👤 %s\n📹 Видео: %s\n\n💬 %s",
                $comment->getUser()->getUsername(),
                $comment->getVideo()->getTitle(),
                mb_substr($comment->getContent(), 0, 200)
            ))
            ->importance(Notification::IMPORTANCE_HIGH);

        $this->sendToAdmins($notification);
    }

    /**
     * Уведомление о критической ошибке системы.
     */
    public function notifySystemError(string $title, string $message): void
    {
        $notification = (new Notification("🔴 $title", ['email', 'chat/telegram']))
            ->content($message)
            ->importance(Notification::IMPORTANCE_URGENT);

        $this->sendToAdmins($notification);
    }

    /**
     * Уведомление о заполнении хранилища.
     */
    public function notifyStorageWarning(string $storageName, float $usagePercent): void
    {
        $notification = (new Notification('⚠️ Предупреждение о хранилище', ['email', 'chat/telegram']))
            ->content(sprintf(
                "Хранилище '%s' заполнено на %.1f%%\n\nРекомендуется очистить место или расширить хранилище.",
                $storageName,
                $usagePercent
            ))
            ->importance(Notification::IMPORTANCE_HIGH);

        $this->sendToAdmins($notification);
    }

    /**
     * Отправить уведомление всем администраторам.
     */
    private function sendToAdmins(Notification $notification): void
    {
        $adminEmail = $this->settings->get('admin_email', 'admin@example.com');
        $recipient = new Recipient($adminEmail);
        
        try {
            $this->notifier->send($notification, $recipient);
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            error_log('Failed to send admin notification: ' . $e->getMessage());
        }
    }
}
