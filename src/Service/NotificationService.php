<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Video;
use App\Repository\NotificationRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private EntityManagerInterface $em,
        private NotificationRepository $notificationRepository,
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function notifyVideoProcessed(Video $video): void
    {
        $user = $video->getCreatedBy();
        if (!$user || !$user->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Your video has been processed')
            ->htmlTemplate('emails/video_processed.html.twig')
            ->context([
                'video' => $video,
                'user' => $user
            ]);

        $this->mailer->send($email);
    }

    public function notifyVideoFailed(Video $video): void
    {
        $user = $video->getCreatedBy();
        if (!$user || !$user->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Video processing failed')
            ->htmlTemplate('emails/video_failed.html.twig')
            ->context([
                'video' => $video,
                'user' => $user
            ]);

        $this->mailer->send($email);
    }

    public function notifyNewVideo(User $author, Video $video): void
    {
        $subscriptions = $this->subscriptionRepository->findBy(['channel' => $author]);
        
        foreach ($subscriptions as $subscription) {
            $subscriber = $subscription->getSubscriber();
            $this->createNotification($subscriber, Notification::TYPE_NEW_VIDEO, [
                'videoId' => $video->getId(),
                'videoTitle' => $video->getTitle(),
                'authorId' => $author->getId(),
                'authorName' => $author->getUsername(),
            ]);
        }
    }

    public function notifyCommentReply(Comment $reply): void
    {
        $parent = $reply->getParent();
        if ($parent === null) {
            return;
        }

        $parentAuthor = $parent->getUser();
        $replyAuthor = $reply->getUser();
        
        // Don't notify yourself
        if ($parentAuthor->getId() === $replyAuthor->getId()) {
            return;
        }

        $this->createNotification($parentAuthor, Notification::TYPE_COMMENT_REPLY, [
            'commentId' => $reply->getId(),
            'videoId' => $reply->getVideo()->getId(),
            'videoTitle' => $reply->getVideo()->getTitle(),
            'fromUserId' => $replyAuthor->getId(),
            'fromUserName' => $replyAuthor->getUsername(),
            'commentPreview' => mb_substr($reply->getContent(), 0, 100),
        ]);
    }

    public function notifyMention(User $mentioned, Comment $comment): void
    {
        $author = $comment->getUser();
        
        // Don't notify yourself
        if ($mentioned->getId() === $author->getId()) {
            return;
        }

        $this->createNotification($mentioned, Notification::TYPE_MENTION, [
            'commentId' => $comment->getId(),
            'videoId' => $comment->getVideo()->getId(),
            'videoTitle' => $comment->getVideo()->getTitle(),
            'fromUserId' => $author->getId(),
            'fromUserName' => $author->getUsername(),
            'commentPreview' => mb_substr($comment->getContent(), 0, 100),
        ]);
    }

    public function notifyNewSubscriber(User $channel, User $subscriber): void
    {
        $this->createNotification($channel, Notification::TYPE_NEW_SUBSCRIBER, [
            'subscriberId' => $subscriber->getId(),
            'subscriberName' => $subscriber->getUsername(),
        ]);
    }

    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
        $this->em->flush();
    }

    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsReadByUser($user);
    }

    /**
     * @return Notification[]
     */
    public function getNotifications(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        return $this->notificationRepository->findByUser($user, $limit, $offset);
    }

    private function createNotification(User $user, string $type, array $data): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setData($data);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }
}
