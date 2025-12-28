<?php

declare(strict_types=1);

namespace App\Tests\Property\Locale;

use App\Controller\LocaleController;
use App\EventSubscriber\LocaleSubscriber;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Property-based tests for URL preservation on locale switch.
 * 
 * **Feature: internationalization, Property 3: URL Preservation on Locale Switch**
 * **Validates: Requirements 2.3, 2.4, 5.4**
 * 
 * Property: For any URL path and query parameters, switching the locale should 
 * redirect to the same URL without adding locale prefixes, preserving all 
 * original path segments and query parameters.
 */
class UrlPreservationPropertyTest extends TestCase
{
    use TestTrait;

    private LocaleController $controller;
    private array $supportedLocales = ['en', 'ru'];

    protected function setUp(): void
    {
        $this->controller = new LocaleController($this->supportedLocales);
    }

    /**
     * Property 3.1: Redirect URL from query parameter is preserved.
     * 
     * For any valid relative URL passed as redirect parameter,
     * the controller SHALL redirect to that exact URL.
     */
    public function testRedirectParameterIsPreserved(): void
    {
        $paths = [
            '/videos',
            '/categories',
            '/video/123',
            '/category/test-category',
            '/search',
            '/profile/user123',
            '/my-videos',
        ];

        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\elements($paths)
        )->then(function (string $locale, string $path): void {
            $request = Request::create(
                sprintf('/locale/%s?redirect=%s', $locale, urlencode($path)),
                'GET'
            );

            $response = $this->controller->switchLocale($locale, $request);

            $this->assertEquals(
                302,
                $response->getStatusCode(),
                'Response should be a redirect'
            );

            $this->assertEquals(
                $path,
                $response->headers->get('Location'),
                sprintf('Redirect should preserve path "%s"', $path)
            );
        });
    }

    /**
     * Property 3.2: Query parameters in redirect URL are preserved.
     * 
     * For any URL with query parameters passed as redirect,
     * the controller SHALL preserve all query parameters in the redirect.
     */
    public function testQueryParametersArePreserved(): void
    {
        $urlsWithParams = [
            '/search?q=test',
            '/videos?page=2',
            '/videos?page=3&sort=date',
            '/category/action?page=1&order=views',
            '/search?q=hello+world&category=1',
        ];

        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\elements($urlsWithParams)
        )->then(function (string $locale, string $urlWithParams): void {
            $request = Request::create(
                sprintf('/locale/%s?redirect=%s', $locale, urlencode($urlWithParams)),
                'GET'
            );

            $response = $this->controller->switchLocale($locale, $request);

            $this->assertEquals(
                $urlWithParams,
                $response->headers->get('Location'),
                sprintf('Redirect should preserve URL with params "%s"', $urlWithParams)
            );
        });
    }

    /**
     * Property 3.3: Locale cookie is set with correct value.
     * 
     * For any supported locale, switching to it SHALL set a cookie
     * with that locale value.
     */
    public function testLocaleCookieIsSet(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale): void {
            $request = Request::create(
                sprintf('/locale/%s?redirect=/videos', $locale),
                'GET'
            );

            $response = $this->controller->switchLocale($locale, $request);

            $cookies = $response->headers->getCookies();
            $localeCookie = null;

            foreach ($cookies as $cookie) {
                if ($cookie->getName() === LocaleSubscriber::LOCALE_COOKIE_NAME) {
                    $localeCookie = $cookie;
                    break;
                }
            }

            $this->assertNotNull(
                $localeCookie,
                'Locale cookie should be set'
            );

            $this->assertEquals(
                $locale,
                $localeCookie->getValue(),
                sprintf('Cookie value should be "%s"', $locale)
            );
        });
    }

    /**
     * Property 3.4: Cookie expiration is approximately 1 year.
     * 
     * For any locale switch, the cookie SHALL have expiration of ~1 year.
     */
    public function testCookieExpirationIsOneYear(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale): void {
            $request = Request::create(
                sprintf('/locale/%s?redirect=/videos', $locale),
                'GET'
            );

            $response = $this->controller->switchLocale($locale, $request);

            $cookies = $response->headers->getCookies();
            $localeCookie = null;

            foreach ($cookies as $cookie) {
                if ($cookie->getName() === LocaleSubscriber::LOCALE_COOKIE_NAME) {
                    $localeCookie = $cookie;
                    break;
                }
            }

            $this->assertNotNull($localeCookie, 'Locale cookie should be set');

            $expectedExpiration = time() + 31536000; // 1 year
            $actualExpiration = $localeCookie->getExpiresTime();

            // Допускаем погрешность в 60 секунд
            $this->assertEqualsWithDelta(
                $expectedExpiration,
                $actualExpiration,
                60,
                'Cookie should expire in approximately 1 year'
            );
        });
    }

    /**
     * Property 3.5: URL does not contain locale prefix after switch.
     * 
     * For any redirect URL, the resulting URL SHALL NOT contain
     * locale prefixes like /en/ or /ru/.
     */
    public function testNoLocalePrefixInRedirectUrl(): void
    {
        $paths = [
            '/videos',
            '/categories',
            '/video/123',
            '/search?q=test',
        ];

        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\elements($paths)
        )->then(function (string $locale, string $path): void {
            $request = Request::create(
                sprintf('/locale/%s?redirect=%s', $locale, urlencode($path)),
                'GET'
            );

            $response = $this->controller->switchLocale($locale, $request);
            $redirectUrl = $response->headers->get('Location');

            // Проверяем, что URL не начинается с /en/ или /ru/
            foreach ($this->supportedLocales as $loc) {
                $this->assertStringNotStartsWithIgnoringCase(
                    '/' . $loc . '/',
                    $redirectUrl,
                    sprintf('Redirect URL should not have locale prefix "/%s/"', $loc)
                );
            }
        });
    }

    /**
     * Property 3.6: getSupportedLocales returns correct locales.
     * 
     * The controller SHALL correctly report supported locales.
     */
    public function testGetSupportedLocalesReturnsCorrectLocales(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale): void {
            $supportedLocales = $this->controller->getSupportedLocales();

            $this->assertContains(
                $locale,
                $supportedLocales,
                sprintf('Locale "%s" should be in supported locales', $locale)
            );
        });
    }

    /**
     * Property 3.6b: Unsupported locales are not in supported list.
     */
    public function testUnsupportedLocalesNotInList(): void
    {
        $unsupportedLocales = ['de', 'fr', 'es', 'it', 'pt', 'zh', 'ja'];

        $this->forAll(
            Generator\elements($unsupportedLocales)
        )->then(function (string $locale): void {
            $supportedLocales = $this->controller->getSupportedLocales();

            $this->assertNotContains(
                $locale,
                $supportedLocales,
                sprintf('Locale "%s" should NOT be in supported locales', $locale)
            );
        });
    }

    /**
     * Property 3.7: Special characters in URL are preserved.
     * 
     * For any URL with special characters (encoded),
     * the controller SHALL preserve them in the redirect.
     */
    public function testSpecialCharactersInUrlArePreserved(): void
    {
        $urlsWithSpecialChars = [
            '/search?q=%D1%82%D0%B5%D1%81%D1%82', // "тест" in URL encoding
            '/video/test-video-name',
            '/category/action-movies',
            '/search?q=hello%20world',
        ];

        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\elements($urlsWithSpecialChars)
        )->then(function (string $locale, string $url): void {
            $request = Request::create(
                sprintf('/locale/%s?redirect=%s', $locale, urlencode($url)),
                'GET'
            );

            $response = $this->controller->switchLocale($locale, $request);

            $this->assertEquals(
                $url,
                $response->headers->get('Location'),
                sprintf('Redirect should preserve URL with special chars "%s"', $url)
            );
        });
    }

    /**
     * Helper assertion for case-insensitive string start check.
     */
    private function assertStringNotStartsWithIgnoringCase(
        string $prefix,
        string $string,
        string $message = ''
    ): void {
        $this->assertFalse(
            str_starts_with(strtolower($string), strtolower($prefix)),
            $message
        );
    }
}
