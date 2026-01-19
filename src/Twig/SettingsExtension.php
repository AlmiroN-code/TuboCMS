<?php

namespace App\Twig;

use App\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    public function __construct(
        private SettingsService $settingsService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', [$this, 'getSetting']),
            new TwigFunction('site_name', [$this->settingsService, 'getSiteName']),
            new TwigFunction('site_logo', [$this->settingsService, 'getSiteLogo']),
            new TwigFunction('site_favicon', [$this->settingsService, 'getSiteFavicon']),
            new TwigFunction('google_search_console_code', [$this, 'getGoogleSearchConsoleCode']),
            new TwigFunction('google_analytics_id', [$this, 'getGoogleAnalyticsId']),
            new TwigFunction('yandex_metrika_id', [$this, 'getYandexMetrikaId']),
            new TwigFunction('facebook_pixel_id', [$this, 'getFacebookPixelId']),
        ];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settingsService->get($key, $default);
    }

    public function getGoogleSearchConsoleCode(): ?string
    {
        return $this->settingsService->get('google_search_console_code');
    }

    public function getGoogleAnalyticsId(): ?string
    {
        return $this->settingsService->get('google_analytics_id');
    }

    public function getYandexMetrikaId(): ?string
    {
        return $this->settingsService->get('yandex_metrika_id');
    }

    public function getFacebookPixelId(): ?string
    {
        return $this->settingsService->get('facebook_pixel_id');
    }
}
