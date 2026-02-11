<?php

namespace App\EventSubscriber;

use App\Service\ContentProtectionService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class MediaRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ContentProtectionService $protectionService,
        #[Autowire(service: 'limiter.media_download')]
        private RateLimiterFactory $mediaDownloadLimiter
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Проверяем только запросы к защищенным медиа
        if (!str_starts_with($request->getPathInfo(), '/secure-media/')) {
            return;
        }

        // Получаем IP пользователя
        $ip = $request->getClientIp();

        // Создаем лимитер для этого IP
        $limiter = $this->mediaDownloadLimiter->create($ip);

        // Проверяем лимит
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $response = new Response(
                'Too many requests. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS
            );

            // Добавляем заголовки с информацией о лимите
            $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
            $response->headers->set('Retry-After', (string) $limit->getRetryAfter()->getTimestamp());

            $event->setResponse($response);
        }
    }
}
