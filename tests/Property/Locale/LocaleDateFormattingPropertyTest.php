<?php

declare(strict_types=1);

namespace App\Tests\Property\Locale;

use App\Twig\FormattingExtension;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Property-based tests for locale-aware date formatting.
 * 
 * **Feature: internationalization, Property 6: Locale-Aware Date Formatting**
 * **Validates: Requirements 8.1, 8.3**
 * 
 * Property: For any date value and supported locale, formatting the date 
 * should produce output consistent with that locale's conventions.
 */
class LocaleDateFormattingPropertyTest extends TestCase
{
    use TestTrait;

    private array $supportedLocales = ['en', 'ru'];
    private string $defaultLocale = 'en';

    /**
     * Create FormattingExtension with a specific locale.
     */
    private function createExtension(string $locale): FormattingExtension
    {
        $request = new Request();
        $request->setLocale($locale);
        
        $requestStack = new RequestStack();
        $requestStack->push($request);
        
        return new FormattingExtension($requestStack, $this->defaultLocale);
    }

    /**
     * Property 6.1: English dates use English format conventions.
     * 
     * For any date, formatting in English locale SHALL produce
     * output with English month names and date order.
     */
    public function testEnglishDatesUseEnglishFormat(): void
    {
        $extension = $this->createExtension('en');
        
        $this->forAll(
            Generator\choose(2020, 2030),
            Generator\choose(1, 12),
            Generator\choose(1, 28)
        )->then(function (int $year, int $month, int $day) use ($extension): void {
            $date = new \DateTime(\sprintf('%04d-%02d-%02d', $year, $month, $day));
            
            $formatted = $extension->formatDate($date, 'medium');
            
            // English format should contain English month name
            $englishMonths = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            $containsEnglishMonth = false;
            foreach ($englishMonths as $monthName) {
                if (\str_contains($formatted, $monthName)) {
                    $containsEnglishMonth = true;
                    break;
                }
            }
            
            $this->assertTrue(
                $containsEnglishMonth,
                \sprintf('English date "%s" should contain English month name', $formatted)
            );
        });
    }


    /**
     * Property 6.2: Russian dates use Russian format conventions.
     * 
     * For any date, formatting in Russian locale SHALL produce
     * output with Russian month names.
     */
    public function testRussianDatesUseRussianFormat(): void
    {
        $extension = $this->createExtension('ru');
        
        $this->forAll(
            Generator\choose(2020, 2030),
            Generator\choose(1, 12),
            Generator\choose(1, 28)
        )->then(function (int $year, int $month, int $day) use ($extension): void {
            $date = new \DateTime(\sprintf('%04d-%02d-%02d', $year, $month, $day));
            
            $formatted = $extension->formatDate($date, 'medium');
            
            // Russian format should contain Russian month name
            $russianMonths = [
                'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
            ];
            
            $containsRussianMonth = false;
            foreach ($russianMonths as $monthName) {
                if (\str_contains($formatted, $monthName)) {
                    $containsRussianMonth = true;
                    break;
                }
            }
            
            $this->assertTrue(
                $containsRussianMonth,
                \sprintf('Russian date "%s" should contain Russian month name', $formatted)
            );
        });
    }

    /**
     * Property 6.3: Date formatting preserves date components.
     * 
     * For any date, the formatted output SHALL contain
     * the correct year, month day, and year values.
     */
    public function testDateFormattingPreservesComponents(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(2020, 2030),
            Generator\choose(1, 12),
            Generator\choose(1, 28)
        )->then(function (string $locale, int $year, int $month, int $day): void {
            $extension = $this->createExtension($locale);
            $date = new \DateTime(\sprintf('%04d-%02d-%02d', $year, $month, $day));
            
            $formatted = $extension->formatDate($date, 'short');
            
            // Short format should contain the year
            $this->assertStringContainsString(
                (string) $year,
                $formatted,
                \sprintf('Formatted date "%s" should contain year %d', $formatted, $year)
            );
            
            // Short format should contain the day
            $this->assertStringContainsString(
                (string) $day,
                $formatted,
                \sprintf('Formatted date "%s" should contain day %d', $formatted, $day)
            );
        });
    }


    /**
     * Property 6.4: Relative time formatting works for all locales.
     * 
     * For any past date, time_ago formatting SHALL produce
     * locale-appropriate relative time string.
     */
    public function testRelativeTimeFormattingWorks(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(1, 365)
        )->then(function (string $locale, int $daysAgo): void {
            $extension = $this->createExtension($locale);
            $date = new \DateTime("-{$daysAgo} days");
            
            $formatted = $extension->formatTimeAgo($date);
            
            // Should not be empty
            $this->assertNotEmpty(
                $formatted,
                'Relative time should not be empty'
            );
            
            // Russian should contain "назад", English should contain "ago"
            if ($locale === 'ru') {
                $this->assertStringContainsString(
                    'назад',
                    $formatted,
                    \sprintf('Russian relative time "%s" should contain "назад"', $formatted)
                );
            } else {
                $this->assertStringContainsString(
                    'ago',
                    $formatted,
                    \sprintf('English relative time "%s" should contain "ago"', $formatted)
                );
            }
        });
    }

    /**
     * Property 6.5: Null dates return empty string.
     * 
     * For null date input, formatting SHALL return empty string.
     */
    public function testNullDatesReturnEmptyString(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\elements(['short', 'medium', 'long', 'datetime'])
        )->then(function (string $locale, string $format): void {
            $extension = $this->createExtension($locale);
            
            $formatted = $extension->formatDate(null, $format);
            
            $this->assertSame(
                '',
                $formatted,
                'Null date should return empty string'
            );
        });
    }

    /**
     * Property 6.6: String dates are parsed correctly.
     * 
     * For any valid date string, formatting SHALL work correctly.
     */
    public function testStringDatesAreParsedCorrectly(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(2020, 2030),
            Generator\choose(1, 12),
            Generator\choose(1, 28)
        )->then(function (string $locale, int $year, int $month, int $day): void {
            $extension = $this->createExtension($locale);
            $dateString = \sprintf('%04d-%02d-%02d', $year, $month, $day);
            
            $formatted = $extension->formatDate($dateString, 'short');
            
            // Should contain the year
            $this->assertStringContainsString(
                (string) $year,
                $formatted,
                \sprintf('Formatted date from string "%s" should contain year %d', $formatted, $year)
            );
        });
    }
}
