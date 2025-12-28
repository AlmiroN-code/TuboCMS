<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension для работы с локалями
 * Requirements: 2.1, 2.2
 */
class LocaleExtension extends AbstractExtension
{
    /**
     * Названия локалей на их родном языке
     */
    private const LOCALE_NAMES = [
        'en' => 'English',
        'ru' => 'Русский',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private array $enabledLocales,
        private string $defaultLocale
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('available_locales', [$this, 'getAvailableLocales']),
            new TwigFunction('current_locale', [$this, 'getCurrentLocale']),
            new TwigFunction('locale_name', [$this, 'getLocaleName']),
        ];
    }

    /**
     * Возвращает список доступных локалей
     */
    public function getAvailableLocales(): array
    {
        return $this->enabledLocales;
    }

    /**
     * Возвращает текущую локаль из запроса
     */
    public function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if ($request === null) {
            return $this->defaultLocale;
        }

        return $request->getLocale();
    }

    /**
     * Возвращает отображаемое название локали
     */
    public function getLocaleName(string $locale): string
    {
        return self::LOCALE_NAMES[$locale] ?? $locale;
    }
}
