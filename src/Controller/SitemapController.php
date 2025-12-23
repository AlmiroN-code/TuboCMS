<?php

namespace App\Controller;

use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function index(VideoRepository $videoRepository): Response
    {
        $videos = $videoRepository->findBy(['status' => 'published'], ['createdAt' => 'DESC']);
        
        $urls = [];
        
        // Homepage
        $urls[] = [
            'loc' => $this->generateUrl('home', [], true),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];
        
        // Videos
        foreach ($videos as $video) {
            $urls[] = [
                'loc' => $this->generateUrl('video_show', ['id' => $video->getId()], true),
                'lastmod' => $video->getUpdatedAt()?->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.8'
            ];
        }
        
        $response = $this->render('sitemap/index.xml.twig', [
            'urls' => $urls
        ]);
        
        $response->headers->set('Content-Type', 'text/xml');
        
        return $response;
    }
}
