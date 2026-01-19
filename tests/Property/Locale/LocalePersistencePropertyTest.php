<?php

declare(strict_types=1);

namespace App\Tests\Property\Locale;

use App\EventSubscriber\LocaleSubscriber;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Property-based tests for locale persistence (cookie round-trip).
 * 
 * **Feature: internationalization, Property 1: Locale Persistence Round Trip**
 * **Validates: Requirements 1.3, 5.1, 5.2**
 * 
 * Property: For any supported locale selected by a user, storing it in a cookie 
 * and then reading it back on subsequent requests should return the same locale value.
 */
class LocalePersistencePropertyTest extends TestCase
{
    use TestTrait;

    private array $supportedLocales = ['en', 'ru'];
    private string $defaultLocale = 'en';

    /**
     * Property 1.1: Cookie locale round-trip preserves value.
     * 
     * For any supported locale stored in cookie,
     * reading it back SHALL return the same locale.
     */
    public function testCookieLocaleRoundTrip(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale): void {
            $subscriber = new LocaleSubscriber($this->defaultLocale, $this->supportedLocales);
            
            // Создаём request с cookie
            $request = Request::create('/');
            $request->cookies->set(LocaleSubscriber::LOCALE_COOKIE_NAME, $locale);
            
            // Создаём сессию
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);
            $session->start();
            
            // Создаём event
            $kernel = $this->createMock(HttpKernelInterface::class);
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
            
            // Обрабатываем запрос
            $subscriber->onKernelRequest($event);
            
            // Проверяем, что локаль установлена правильно
            $this->assertEquals(
                $locale,
                $request->getLocale(),
                sprintf('Cookie locale "%s" should be preserved in request', $locale)
            );
            
            // Проверяем, что локаль сохранена в сессии
            $this->assertEquals(
                $locale,
                $session->get('_locale'),
                sprintf('Cookie locale "%s" should be saved to session', $locale)
            );
        });
    }

    /**
     * Property 1.2: Invalid cookie locale falls back to Accept-Language or default.
     * 
     * For any unsupported locale in cookie,
     * the system SHALL fall back to Accept-Language or default locale.
     */
    public function testInvalidCookieLocaleFallsBack(): void
    {
        $unsupportedLocales = ['de', 'fr', 'es', 'it', 'pt', 'zh', 'ja'];

        $this->forAll(
            Generator\elements($unsupportedLocales)
        )->then(function (string $invalidLocale): void {
            $subscriber = new LocaleSubscriber($this->defaultLocale, $this->supportedLocales);
            
            // Создаём request с невалидной cookie
            $request = Request::create('/');
            $request->cookies->set(LocaleSubscriber::LOCALE_COOKIE_NAME, $invalidLocale);
            
            // Создаём сессию
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);
            $session->start();
            
            // Создаём event
            $kernel = $this->createMock(HttpKernelInterface::class);
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
            
            // Обрабатываем запрос
            $subscriber->onKernelRequest($event);
            
            // Должен использоваться default locale
            $this->assertEquals(
                $this->defaultLocale,
                $request->getLocale(),
                sprintf('Invalid cookie locale "%s" should fall back to default "%s"', $invalidLocale, $this->defaultLocale)
            );
        });
    }

    /**
     * Property 1.3: Cookie takes priority over Accept-Language.
     * 
     * For any request with both cookie and Accept-Language,
     * cookie locale SHALL take priority.
     */
    public function testCookieTakesPriorityOverAcceptLanguage(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\elements($this->supportedLocales)
        )->when(function (string $cookieLocale, string $headerLocale): bool {
            // Тестируем только когда локали разные
            return $cookieLocale !== $headerLocale;
        })->then(function (string $cookieLocale, string $headerLocale): void {
            $subscriber = new LocaleSubscriber($this->defaultLocale, $this->supportedLocales);
            
            // Создаём request с cookie и Accept-Language
            $request = Request::create('/');
            $request->cookies->set(LocaleSubscriber::LOCALE_COOKIE_NAME, $cookieLocale);
            $request->headers->set('Accept-Language', sprintf('%s;q=1.0', $headerLocale));
            
            // Создаём сессию
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);
            $session->start();
            
            // Создаём event
            $kernel = $this->createMock(HttpKernelInterface::class);
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
            
            // Обрабатываем запрос
            $subscriber->onKernelRequest($event);
            
            // Cookie должна иметь приоритет
            $this->assertEquals(
                $cookieLocale,
                $request->getLocale(),
                sprintf('Cookie locale "%s" should take priority over Accept-Language "%s"', $cookieLocale, $headerLocale)
            );
        });
    }

    /**
     * Property 1.4: Session stores locale after request processing.
     * 
     * For any valid locale detection,
     * the locale SHALL be stored in session.
     */
    public function testSessionStoresLocale(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale): void {
            $subscriber = new LocaleSubscriber($this->defaultLocale, $this->supportedLocales);
            
            // Создаём request с Accept-Language (без cookie)
            $request = Request::create('/');
            $request->headers->set('Accept-Language', sprintf('%s;q=1.0', $locale));
            
            // Создаём сессию
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);
            $session->start();
            
            // Создаём event
            $kernel = $this->createMock(HttpKernelInterface::class);
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
            
            // Обрабатываем запрос
            $subscriber->onKernelRequest($event);
            
            // Проверяем, что локаль сохранена в сессии
            $this->assertEquals(
                $locale,
                $session->get('_locale'),
                sprintf('Locale "%s" should be stored in session', $locale)
            );
        });
    }

    /**
     * Property 1.5: Sub-requests are skipped.
     * 
     * For any sub-request, the subscriber SHALL not modify locale.
     */
    public function testSubRequestsAreSkipped(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale): void {
            $subscriber = new LocaleSubscriber($this->defaultLocale, $this->supportedLocales);
            
            // Создаём request
            $request = Request::create('/');
            $request->cookies->set(LocaleSubscriber::LOCALE_COOKIE_NAME, $locale);
            
            // Устанавливаем начальную локаль
            $initialLocale = 'en';
            $request->setLocale($initialLocale);
            
            // Создаём SUB_REQUEST event
            $kernel = $this->createMock(HttpKernelInterface::class);
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
            
            // Обрабатываем запрос
            $subscriber->onKernelRequest($event);
            
            // Локаль не должна измениться для sub-request
            $this->assertEquals(
                $initialLocale,
                $request->getLocale(),
                'Sub-request should not modify locale'
            );
        });
    }

    /**
     * Property 1.6: Empty cookie falls back correctly.
     * 
     * For any empty or whitespace cookie value,
     * the system SHALL fall back to Accept-Language or default.
     */
    public function testEmptyCookieFallsBack(): void
    {
        $this->forAll(
            Generator\elements(['', ' ', '  ', null]),
            Generator\elements($this->supportedLocales)
        )->then(function ($emptyCookie, string $acceptLanguageLocale): void {
            $subscriber = new LocaleSubscriber($this->defaultLocale, $this->supportedLocales);
            
            // Создаём request с пустой cookie и Accept-Language
            $request = Request::create('/');
            if ($emptyCookie !== null) {
                $request->cookies->set(LocaleSubscriber::LOCALE_COOKIE_NAME, $emptyCookie);
            }
            $request->headers->set('Accept-Language', sprintf('%s;q=1.0', $acceptLanguageLocale));
            
            // Создаём сессию
            $session = new Session(new MockArraySessionStorage());
            $request->setSession($session);
            $session->start();
            
            // Создаём event
            $kernel = $this->createMock(HttpKernelInterface::class);
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
            
            // Обрабатываем запрос
            $subscriber->onKernelRequest($event);
            
            // Должен использоваться Accept-Language
            $this->assertEquals(
                $acceptLanguageLocale,
                $request->getLocale(),
                sprintf('Empty cookie should fall back to Accept-Language "%s"', $acceptLanguageLocale)
            );
        });
    }
}
