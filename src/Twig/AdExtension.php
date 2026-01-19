<?php

namespace App\Twig;

use App\Service\AdService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\Environment;

class AdExtension extends AbstractExtension
{
    public function __construct(
        private AdService $adService,
        private Environment $twig
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('show_ad', [$this, 'showAd'], ['is_safe' => ['html']]),
            new TwigFunction('show_ads', [$this, 'showAds'], ['is_safe' => ['html']]),
            new TwigFunction('ad_script', [$this, 'adScript'], ['is_safe' => ['html']]),
        ];
    }

    public function showAd(string $placement, array $context = []): string
    {
        $ad = $this->adService->getAdForPlacement($placement, $context);
        
        if (!$ad) {
            return '';
        }

        try {
            return $this->twig->render('ads/display.html.twig', [
                'ad' => $ad,
                'placement' => $placement,
            ]);
        } catch (\Exception $e) {
            return '<!-- Ad render error: ' . $e->getMessage() . ' -->';
        }
    }

    public function showAds(string $placement, int $limit = 3, array $context = []): string
    {
        $ads = $this->adService->getAdsForPlacement($placement, $limit, $context);
        
        if (empty($ads)) {
            return '';
        }

        try {
            return $this->twig->render('ads/display_multiple.html.twig', [
                'ads' => $ads,
                'placement' => $placement,
            ]);
        } catch (\Exception $e) {
            return '<!-- Ads render error: ' . $e->getMessage() . ' -->';
        }
    }

    public function adScript(string $placement, array $context = []): string
    {
        $queryParams = http_build_query(array_merge($context, ['placement' => $placement]));
        
        return sprintf(
            '<script async src="%s/ads/js/%s?%s"></script>',
            $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'],
            $placement,
            $queryParams
        );
    }
}