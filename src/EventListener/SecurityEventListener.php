<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SecurityEventListener
{
    public function __construct(
        private LoggerInterface $logger,
        private RequestStack $requestStack
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $event->getUser();

        $this->logger->info('User login successful', [
            'username' => $user->getUserIdentifier(),
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'timestamp' => new \DateTimeImmutable()
        ]);
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $exception = $event->getException();

        $this->logger->warning('User login failed', [
            'username' => $request?->request->get('email', 'unknown'),
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'reason' => $exception->getMessage(),
            'timestamp' => new \DateTimeImmutable()
        ]);
    }

    #[AsEventListener(event: AuthenticationSuccessEvent::class)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        if (method_exists($user, 'getUserIdentifier')) {
            $this->logger->info('Authentication successful', [
                'username' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'timestamp' => new \DateTimeImmutable()
            ]);
        }
    }

    #[AsEventListener(event: AuthenticationFailureEvent::class)]
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getAuthenticationException();
        
        $this->logger->warning('Authentication failed', [
            'reason' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'timestamp' => new \DateTimeImmutable()
        ]);
    }
}