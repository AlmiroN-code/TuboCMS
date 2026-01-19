<?php

namespace App\Scheduler\Handler;

use App\Scheduler\Message\GenerateSitemapMessage;
use App\Repository\VideoRepository;
use App\Repository\CategoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class GenerateSitemapHandler
{
    public function __construct(
        private VideoRepository $videoRepository,
        private CategoryRepository $categoryRepository,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    public function __invoke(GenerateSitemapMessage $message): void
    {
        $this->logger->info('Начинаем генерацию sitemap');

        try {
            $sitemapPath = $this->projectDir . '/public/sitemap.xml';
            
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            
            // Главная страница
            $xml .= $this->createUrlEntry('/', '1.0', 'daily');
            
            // Категории
            $categories = $this->categoryRepository->findAll();
            foreach ($categories as $category) {
                $xml .= $this->createUrlEntry(
                    '/category/' . $category->getSlug(),
                    '0.8',
                    'daily'
                );
            }
            
            // Видео
            $videos = $this->videoRepository->findPublished(1000);
            foreach ($videos as $video) {
                $xml .= $this->createUrlEntry(
                    '/video/' . $video->getSlug(),
                    '0.6',
                    'weekly',
                    $video->getUpdatedAt()?->format('Y-m-d')
                );
            }
            
            $xml .= '</urlset>';
            
            file_put_contents($sitemapPath, $xml);
            
            $this->logger->info('Sitemap успешно сгенерирован', [
                'videos_count' => count($videos),
                'categories_count' => count($categories),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка генерации sitemap: ' . $e->getMessage());
        }
    }

    private function createUrlEntry(string $path, string $priority, string $changefreq, ?string $lastmod = null): string
    {
        $url = rtrim($this->urlGenerator->getContext()->getScheme() . '://' . $this->urlGenerator->getContext()->getHost(), '/') . $path;
        
        $entry = "  <url>\n";
        $entry .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
        if ($lastmod) {
            $entry .= "    <lastmod>{$lastmod}</lastmod>\n";
        }
        $entry .= "    <changefreq>{$changefreq}</changefreq>\n";
        $entry .= "    <priority>{$priority}</priority>\n";
        $entry .= "  </url>\n";
        
        return $entry;
    }
}
