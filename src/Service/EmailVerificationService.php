<?php

namespace App\Service;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $fromEmail
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        // Удаляем старые токены
        $this->removeOldTokens($user);

        // Создаём новый токен
        $token = new EmailVerificationToken();
        $token->setUser($user);

        $this->em->persist($token);
        $this->em->flush();

        // Генерируем ссылку
        $verificationUrl = $this->urlGenerator->generate('app_verify_email', [
            'token' => $token->getToken()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // Отправляем email
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Подтвердите ваш email')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiresAt' => $token->getExpiresAt()
            ]);

        $this->mailer->send($email);
    }

    public function verifyEmail(string $token): ?User
    {
        $tokenEntity = $this->em->getRepository(EmailVerificationToken::class)
            ->findOneBy(['token' => $token]);

        if (!$tokenEntity || $tokenEntity->isExpired()) {
            return null;
        }

        $user = $tokenEntity->getUser();
        $user->setVerified(true);

        $this->em->remove($tokenEntity);
        $this->em->flush();

        return $user;
    }

    private function removeOldTokens(User $user): void
    {
        $tokens = $this->em->getRepository(EmailVerificationToken::class)
            ->findBy(['user' => $user]);

        foreach ($tokens as $token) {
            $this->em->remove($token);
        }

        $this->em->flush();
    }
}
