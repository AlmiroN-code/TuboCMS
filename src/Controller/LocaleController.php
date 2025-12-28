<?php

declare(strict_types=1);

namespace App\Controller;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * LocaleController - контроллер для переключения языка интерфейса.
 * 
 * Requirements: 2.3, 5.1
 */
class LocaleController extends AbstractController
{
    private const COOKIE_LIFETIME_SECONDS = 31536000; // 1 год (365 * 24 * 60 * 60)

    public function __construct(
        private readonly array $supportedLocales
    ) {
    }

    /**
     * Переключает локаль и редиректит на указанный URL.
     * 
     * @param string $locale Код локали (en, ru)
     * @param Request $request HTTP запрос
     */
    #[Route('/locale/{locale}', name: 'app_locale_switch', methods: ['GET'])]
    public function switchLocale(string $locale, Request $request): Response
    {
        // Валидируем локаль
        if (!$this->isLocaleSupported($locale)) {
            // Если локаль не поддерживается, редиректим на главную
            return $this->redirectToRoute('app_home');
        }

        // Получаем URL для редиректа
        $redirectUrl = $this->getRedirectUrl($request);

        // Создаём response с редиректом
        $response = new RedirectResponse($redirectUrl);

        // Устанавливаем cookie с локалью (1 год)
        $cookie = Cookie::create(LocaleSubscriber::LOCALE_COOKIE_NAME)
            ->withValue($locale)
            ->withExpires(time() + self::COOKIE_LIFETIME_SECONDS)
            ->withPath('/')
            ->withSecure(false)
            ->withHttpOnly(true)
            ->withSameSite('lax');

        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Проверяет, поддерживается ли локаль.
     */
    private function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }

    /**
     * Получает URL для редиректа из параметра redirect или referer.
     */
    private function getRedirectUrl(Request $request): string
    {
        // 1. Проверяем параметр redirect в query string
        $redirectParam = $request->query->get('redirect');
        if ($redirectParam && $this->isValidRedirectUrl($redirectParam)) {
            return $redirectParam;
        }

        // 2. Проверяем Referer header
        $referer = $request->headers->get('referer');
        if ($referer && $this->isValidRedirectUrl($referer)) {
            return $referer;
        }

        // 3. Fallback на главную страницу
        return $this->generateUrl('app_home');
    }

    /**
     * Проверяет, что URL безопасен для редиректа (относительный или тот же хост).
     */
    private function isValidRedirectUrl(string $url): bool
    {
        // Пустой URL не валиден
        if (empty($url)) {
            return false;
        }

        // Относительные URL всегда валидны
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // Для абсолютных URL проверяем, что это тот же хост
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['host'])) {
            return false;
        }

        // Получаем текущий хост из $_SERVER
        $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        
        return $parsedUrl['host'] === $currentHost;
    }

    /**
     * Возвращает список поддерживаемых локалей.
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }
}
