<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Video;
use App\Entity\VideoView;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Сервис для отслеживания просмотров видео с геолокацией
 */
class VideoViewTracker
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GeoIpService $geoIpService,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * Записать просмотр видео
     */
    public function trackView(Video $video, ?User $user = null): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $ipAddress = $request->getClientIp() ?? '127.0.0.1';
        
        // Определяем страну по IP
        $countryCode = $this->geoIpService->getCountryCode($ipAddress);

        // Обновляем IP и страну пользователя если он авторизован
        if ($user && !$user->isCountryManuallySet()) {
            $user->setLastIpAddress($ipAddress);
            if ($countryCode) {
                $user->setCountryCode($countryCode);
            }
        }

        // Создаем запись просмотра
        $videoView = new VideoView();
        $videoView->setVideo($video);
        $videoView->setUser($user);
        $videoView->setIpAddress($ipAddress);
        $videoView->setCountryCode($countryCode);
        $videoView->setUserAgent($request->headers->get('User-Agent'));
        $videoView->setReferer($request->headers->get('Referer'));

        $this->em->persist($videoView);
        
        // Увеличиваем счетчик просмотров видео
        $video->incrementViews();
        
        $this->em->flush();
    }
}
