<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/admin/system')]
#[IsGranted('ROLE_ADMIN')]
class AdminSystemController extends AbstractController
{
    #[Route('/messenger', name: 'admin_system_messenger')]
    public function messenger(
        #[Autowire(service: 'messenger.transport.async')] $asyncTransport,
        #[Autowire(service: 'messenger.transport.failed')] $failedTransport
    ): Response
    {
        $asyncCount = 0;
        $failedCount = 0;
        
        if ($asyncTransport instanceof MessageCountAwareInterface) {
            $asyncCount = $asyncTransport->getMessageCount();
        }
        
        if ($failedTransport instanceof MessageCountAwareInterface) {
            $failedCount = $failedTransport->getMessageCount();
        }
        
        return $this->render('admin/system/messenger.html.twig', [
            'async_count' => $asyncCount,
            'failed_count' => $failedCount,
        ]);
    }
    
    #[Route('/rate-limiter', name: 'admin_system_rate_limiter')]
    public function rateLimiter(): Response
    {
        $limiters = [
            'video_upload' => [
                'name' => 'Video Upload',
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '1 hour',
            ],
            'comment_post' => [
                'name' => 'Comment Posting',
                'policy' => 'fixed_window',
                'limit' => 10,
                'interval' => '5 minutes',
            ],
            'api_requests' => [
                'name' => 'API Requests',
                'policy' => 'token_bucket',
                'limit' => 100,
                'interval' => '1 minute',
            ],
        ];
        
        return $this->render('admin/system/rate_limiter.html.twig', [
            'limiters' => $limiters,
        ]);
    }
    
    #[Route('/mailer', name: 'admin_system_mailer')]
    public function mailer(): Response
    {
        return $this->render('admin/system/mailer.html.twig');
    }
}
