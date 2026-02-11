<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Psr\Log\LoggerInterface;

class PushNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
        private string $vapidSubject
    ) {
    }

    public function sendToUser(User $user, string $title, string $body, string $url = '/'): bool
    {
        $subscriptions = $this->em->getRepository(PushSubscription::class)
            ->findBy(['user' => $user]);

        if (empty($subscriptions)) {
            $this->logger->info('No push subscriptions found for user', [
                'user_id' => $user->getId()
            ]);
            return false;
        }

        $auth = [
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ]
        ];

        $webPush = new WebPush($auth);

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->getEndpoint(),
                    'keys' => [
                        'p256dh' => $sub->getP256dh(),
                        'auth' => $sub->getAuth()
                    ]
                ]);

                $payload = json_encode([
                    'title' => $title,
                    'body' => $body,
                    'url' => $url
                ]);

                $webPush->queueNotification($subscription, $payload);
            } catch (\Exception $e) {
                $this->logger->error('Failed to queue push notification', [
                    'error' => $e->getMessage(),
                    'subscription_id' => $sub->getId()
                ]);
            }
        }

        try {
            $results = $webPush->flush();
            
            $successCount = 0;
            $failCount = 0;

            foreach ($results as $result) {
                if ($result->isSuccess()) {
                    $successCount++;
                } else {
                    $failCount++;
                    
                    // Удаляем невалидные подписки
                    if ($result->isSubscriptionExpired()) {
                        $endpoint = $result->getEndpoint();
                        $expiredSub = $this->em->getRepository(PushSubscription::class)
                            ->findOneBy(['endpoint' => $endpoint]);
                        
                        if ($expiredSub) {
                            $this->em->remove($expiredSub);
                            $this->logger->info('Removed expired push subscription', [
                                'endpoint' => $endpoint
                            ]);
                        }
                    }
                }
            }

            $this->em->flush();

            $this->logger->info('Push notifications sent', [
                'user_id' => $user->getId(),
                'success' => $successCount,
                'failed' => $failCount
            ]);

            return $successCount > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send push notifications', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);
            return false;
        }
    }

    public function sendToMultipleUsers(array $users, string $title, string $body, string $url = '/'): int
    {
        $sentCount = 0;
        
        foreach ($users as $user) {
            if ($this->sendToUser($user, $title, $body, $url)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    public function testNotification(User $user): bool
    {
        return $this->sendToUser(
            $user,
            'Тестовое уведомление',
            'Push уведомления работают!',
            '/'
        );
    }
}
