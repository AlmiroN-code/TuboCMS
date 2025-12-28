<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * LocaleSubscriber - определяет и устанавливает локаль для каждого запроса.
 * 
 * Порядок определения локали:
 * 1. Cookie '_locale'
 * 2. Accept-Language header браузера
 * 3. Default locale (en)
 * 
 * Requirements: 1.1, 1.3, 1.4, 5.2
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    public const LOCALE_COOKIE_NAME = '_locale';

    public function __construct(
        private readonly string $defaultLocale,
        private readonly array $supportedLocales
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Высокий приоритет, чтобы выполниться до роутера
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Пропускаем sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $locale = $this->determineLocale($request);
        
        $request->setLocale($locale);
        
        // Сохраняем в сессию если она доступна
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->set('_locale', $locale);
        }
    }

    /**
     * Определяет локаль из различных источников.
     */
    private function determineLocale($request): string
    {
        // 1. Проверяем cookie
        $cookieLocale = $request->cookies->get(self::LOCALE_COOKIE_NAME);
        if ($cookieLocale && $this->isLocaleSupported($cookieLocale)) {
            return $cookieLocale;
        }

        // 2. Проверяем Accept-Language header
        $acceptLanguage = $request->headers->get('Accept-Language');
        if ($acceptLanguage) {
            $browserLocale = $this->parseAcceptLanguage($acceptLanguage);
            if ($browserLocale && $this->isLocaleSupported($browserLocale)) {
                return $browserLocale;
            }
        }

        // 3. Fallback на default locale
        return $this->defaultLocale;
    }

    /**
     * Парсит Accept-Language header и возвращает наиболее предпочтительную поддерживаемую локаль.
     */
    public function parseAcceptLanguage(string $acceptLanguage): ?string
    {
        $locales = [];
        
        // Парсим Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            
            // Разделяем язык и качество (q=0.9)
            $segments = explode(';', $part);
            $locale = trim($segments[0]);
            
            // Извлекаем качество (по умолчанию 1.0)
            $quality = 1.0;
            if (isset($segments[1])) {
                $qPart = trim($segments[1]);
                if (str_starts_with($qPart, 'q=')) {
                    $quality = (float) substr($qPart, 2);
                }
            }
            
            // Нормализуем локаль (ru-RU -> ru)
            $normalizedLocale = $this->normalizeLocale($locale);
            
            if ($normalizedLocale && !isset($locales[$normalizedLocale])) {
                $locales[$normalizedLocale] = $quality;
            }
        }
        
        // Сортируем по качеству (убывание)
        arsort($locales);
        
        // Возвращаем первую поддерживаемую локаль
        foreach (array_keys($locales) as $locale) {
            if ($this->isLocaleSupported($locale)) {
                return $locale;
            }
        }
        
        return null;
    }

    /**
     * Нормализует локаль (ru-RU -> ru, en-US -> en).
     */
    private function normalizeLocale(string $locale): ?string
    {
        $locale = strtolower(trim($locale));
        
        if (empty($locale)) {
            return null;
        }
        
        // Берём только языковую часть (до дефиса или подчёркивания)
        $parts = preg_split('/[-_]/', $locale);
        
        return $parts[0] ?? null;
    }

    /**
     * Проверяет, поддерживается ли локаль.
     */
    public function isLocaleSupported(string $locale): bool
    {
        $normalized = $this->normalizeLocale($locale);
        return $normalized !== null && in_array($normalized, $this->supportedLocales, true);
    }

    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }
}
