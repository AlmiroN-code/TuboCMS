<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Video;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail
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
}
