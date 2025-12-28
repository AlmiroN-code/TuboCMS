<?php

declare(strict_types=1);

namespace App\Tests\Property\Locale;

use App\EventSubscriber\LocaleSubscriber;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for browser locale detection.
 * 
 * **Feature: internationalization, Property 2: Browser Locale Detection**
 * **Validates: Requirements 1.1, 1.4**
 * 
 * Property: For any Accept-Language header containing a supported locale, 
 * the system should detect and apply that locale; for unsupported locales, 
 * the system should fall back to the default locale (English).
 */
class BrowserLocaleDetectionPropertyTest extends TestCase
{
    use TestTrait;

    private LocaleSubscriber $subscriber;
    private array $supportedLocales = ['en', 'ru'];
    private string $defaultLocale = 'en';

    protected function setUp(): void
    {
        $this->subscriber = new LocaleSubscriber(
            $this->defaultLocale,
            $this->supportedLocales
        );
    }

    /**
     * Property 2.1: Supported locale in Accept-Language is detected.
     * 
     * For any supported locale appearing in Accept-Language header,
     * parseAcceptLanguage SHALL return that locale.
     */
    public function testSupportedLocaleIsDetected(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\elements(['1.0', '0.9', '0.8', '0.7', '0.5'])
        )->then(function (string $locale, string $quality): void {
            $acceptLanguage = sprintf('%s;q=%s', $locale, $quality);
            
            $detected = $this->subscriber->parseAcceptLanguage($acceptLanguage);
            
            $this->assertEquals(
                $locale,
                $detected,
                sprintf('Accept-Language "%s" should detect locale "%s"', $acceptLanguage, $locale)
            );
        });
    }

    /**
     * Property 2.2: Regional variants are normalized to base locale.
     * 
     * For any supported locale with regional suffix (ru-RU, en-US),
     * parseAcceptLanguage SHALL return the base locale.
     */
    public function testRegionalVariantsAreNormalized(): void
    {
        $regionalVariants = [
            'ru-RU' => 'ru',
            'ru-UA' => 'ru',
            'en-US' => 'en',
            'en-GB' => 'en',
            'en-AU' => 'en',
        ];

        $this->forAll(
            Generator\elements(array_keys($regionalVariants))
        )->then(function (string $regional) use ($regionalVariants): void {
            $expected = $regionalVariants[$regional];
            
            $detected = $this->subscriber->parseAcceptLanguage($regional);
            
            $this->assertEquals(
                $expected,
                $detected,
                sprintf('Regional variant "%s" should normalize to "%s"', $regional, $expected)
            );
        });
    }

    /**
     * Property 2.3: Unsupported locale falls back to null (then default).
     * 
     * For any unsupported locale in Accept-Language header,
     * parseAcceptLanguage SHALL return null.
     */
    public function testUnsupportedLocaleReturnsNull(): void
    {
        $unsupportedLocales = ['de', 'fr', 'es', 'it', 'pt', 'zh', 'ja', 'ko', 'ar'];

        $this->forAll(
            Generator\elements($unsupportedLocales)
        )->then(function (string $locale): void {
            $acceptLanguage = sprintf('%s;q=1.0', $locale);
            
            $detected = $this->subscriber->parseAcceptLanguage($acceptLanguage);
            
            $this->assertNull(
                $detected,
                sprintf('Unsupported locale "%s" should return null', $locale)
            );
        });
    }

    /**
     * Property 2.4: Quality-based priority is respected.
     * 
     * For any Accept-Language with multiple locales and different qualities,
     * parseAcceptLanguage SHALL return the supported locale with highest quality.
     */
    public function testQualityPriorityIsRespected(): void
    {
        // Тест: ru с высоким приоритетом, en с низким
        $this->forAll(
            Generator\elements(['0.9', '0.8', '0.7']),
            Generator\elements(['0.5', '0.4', '0.3'])
        )->then(function (string $highQuality, string $lowQuality): void {
            $acceptLanguage = sprintf('ru;q=%s,en;q=%s', $highQuality, $lowQuality);
            
            $detected = $this->subscriber->parseAcceptLanguage($acceptLanguage);
            
            $this->assertEquals(
                'ru',
                $detected,
                sprintf('Higher quality locale should be selected from "%s"', $acceptLanguage)
            );
        });
    }

    /**
     * Property 2.5: First supported locale is selected when unsupported has higher priority.
     * 
     * For any Accept-Language where unsupported locale has highest quality,
     * parseAcceptLanguage SHALL return the first supported locale.
     */
    public function testFirstSupportedLocaleSelectedWhenUnsupportedHasHigherPriority(): void
    {
        $this->forAll(
            Generator\elements(['de', 'fr', 'es', 'it']),
            Generator\elements($this->supportedLocales)
        )->then(function (string $unsupported, string $supported): void {
            $acceptLanguage = sprintf('%s;q=1.0,%s;q=0.8', $unsupported, $supported);
            
            $detected = $this->subscriber->parseAcceptLanguage($acceptLanguage);
            
            $this->assertEquals(
                $supported,
                $detected,
                sprintf('Should select supported "%s" from "%s"', $supported, $acceptLanguage)
            );
        });
    }

    /**
     * Property 2.6: Empty Accept-Language returns null.
     */
    public function testEmptyAcceptLanguageReturnsNull(): void
    {
        $this->forAll(
            Generator\elements(['', ' ', '  ', "\t", "\n"])
        )->then(function (string $empty): void {
            $detected = $this->subscriber->parseAcceptLanguage($empty);
            
            $this->assertNull(
                $detected,
                'Empty Accept-Language should return null'
            );
        });
    }

    /**
     * Property 2.7: Complex Accept-Language headers are parsed correctly.
     * 
     * For any complex Accept-Language header with multiple locales,
     * parseAcceptLanguage SHALL correctly identify supported locales.
     */
    public function testComplexAcceptLanguageHeaders(): void
    {
        $complexHeaders = [
            'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7' => 'ru',
            'en-US,en;q=0.9,ru;q=0.8' => 'en',
            'de-DE,de;q=0.9,ru;q=0.8,en;q=0.7' => 'ru',
            'fr-FR,fr;q=0.9,de;q=0.8,en;q=0.7' => 'en',
            '*;q=0.5,ru;q=0.9' => 'ru',
        ];

        $this->forAll(
            Generator\elements(array_keys($complexHeaders))
        )->then(function (string $header) use ($complexHeaders): void {
            $expected = $complexHeaders[$header];
            
            $detected = $this->subscriber->parseAcceptLanguage($header);
            
            $this->assertEquals(
                $expected,
                $detected,
                sprintf('Complex header "%s" should detect "%s"', $header, $expected)
            );
        });
    }

    /**
     * Property 2.8: isLocaleSupported correctly identifies supported locales.
     */
    public function testIsLocaleSupportedProperty(): void
    {
        $this->forAll(
            Generator\elements(array_merge(
                $this->supportedLocales,
                ['de', 'fr', 'es', 'it', 'pt']
            ))
        )->then(function (string $locale): void {
            $isSupported = $this->subscriber->isLocaleSupported($locale);
            $expected = in_array($locale, $this->supportedLocales, true);
            
            $this->assertEquals(
                $expected,
                $isSupported,
                sprintf('isLocaleSupported("%s") should return %s', $locale, $expected ? 'true' : 'false')
            );
        });
    }

    /**
     * Property 2.9: Default quality (1.0) is applied when not specified.
     */
    public function testDefaultQualityIsApplied(): void
    {
        // Локаль без q= должна иметь приоритет 1.0
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale): void {
            // Локаль без качества vs локаль с q=0.9
            $otherLocale = $locale === 'ru' ? 'en' : 'ru';
            $acceptLanguage = sprintf('%s,%s;q=0.9', $locale, $otherLocale);
            
            $detected = $this->subscriber->parseAcceptLanguage($acceptLanguage);
            
            $this->assertEquals(
                $locale,
                $detected,
                sprintf('Locale without quality should have priority 1.0 in "%s"', $acceptLanguage)
            );
        });
    }
}
