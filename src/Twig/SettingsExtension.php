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
        ];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settingsService->get($key, $default);
    }
}
