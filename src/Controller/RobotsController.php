<?php

namespace App\Controller;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RobotsController extends AbstractController
{
    public function __construct(
        private SettingsService $settingsService
    ) {
    }

    #[Route('/robots.txt', name: 'robots_txt', defaults: ['_format' => 'txt'])]
    public function index(): Response
    {
        $siteUrl = $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $siteUrl = rtrim($siteUrl, '/');
        
        // Получаем настройки из БД или используем дефолтные
        $robotsContent = $this->settingsService->get('robots_txt_content');
        
        if (empty($robotsContent)) {
            // Дефолтный robots.txt
            $robotsContent = $this->getDefaultRobotsTxt($siteUrl);
        } else {
            // Заменяем плейсхолдер {SITE_URL} на реальный URL
            $robotsContent = str_replace('{SITE_URL}', $siteUrl, $robotsContent);
        }
        
        $response = new Response($robotsContent);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        
        return $response;
    }

    private function getDefaultRobotsTxt(string $siteUrl): string
    {
        return <<<ROBOTS
# Robots.txt for {$siteUrl}
# Generated dynamically

User-agent: *

# Allow main content
Allow: /
Allow: /videos/
Allow: /categories/
Allow: /tags/
Allow: /models/
Allow: /members/

# Disallow admin and private areas
Disallow: /admin/
Disallow: /login
Disallow: /register
Disallow: /logout
Disallow: /profile/edit
Disallow: /my-videos/
Disallow: /upload/
Disallow: /api/

# Disallow filtered/sorted pages (duplicate content)
Disallow: /*?sort=
Disallow: /*?filter=
Disallow: /*?duration=
Disallow: /*&sort=
Disallow: /*&filter=
Disallow: /*&duration=

# Disallow deep pagination (page > 10)
Disallow: /*?page=1[1-9]
Disallow: /*?page=[2-9][0-9]
Disallow: /*?page=[0-9][0-9][0-9]

# Disallow media files from direct indexing
Disallow: /media/videos/
Disallow: /storage/

# Allow media thumbnails
Allow: /media/posters/
Allow: /media/previews/
Allow: /media/avatars/

# Crawl-delay (optional, uncomment if needed)
# Crawl-delay: 1

# Sitemaps
Sitemap: {$siteUrl}/sitemap.xml
Sitemap: {$siteUrl}/sitemap-videos.xml
Sitemap: {$siteUrl}/sitemap-categories.xml
Sitemap: {$siteUrl}/sitemap-models.xml
ROBOTS;
    }
}
