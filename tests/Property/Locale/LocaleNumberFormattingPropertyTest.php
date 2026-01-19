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
 * Property-based tests for locale-aware number formatting.
 * 
 * **Feature: internationalization, Property 7: Locale-Aware Number Formatting**
 * **Validates: Requirements 8.2**
 * 
 * Property: For any numeric value and supported locale, formatting the number 
 * should use the locale's decimal and thousand separators.
 */
class LocaleNumberFormattingPropertyTest extends TestCase
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
     * Property 7.1: English numbers use comma as thousand separator.
     * 
     * For any large number, English locale SHALL use comma
     * as thousand separator (e.g., 1,234,567).
     */
    public function testEnglishNumbersUseCommaSeparator(): void
    {
        $extension = $this->createExtension('en');
        
        $this->forAll(
            Generator\choose(1000, 9999999)
        )->then(function (int $number) use ($extension): void {
            $formatted = $extension->formatNumber($number);
            
            // Numbers >= 1000 should contain comma separator
            if ($number >= 1000) {
                $this->assertStringContainsString(
                    ',',
                    $formatted,
                    \sprintf('English number "%s" should contain comma separator for %d', $formatted, $number)
                );
            }
            
            // Should not contain space as separator
            $this->assertStringNotContainsString(
                ' ',
                $formatted,
                \sprintf('English number "%s" should not contain space separator', $formatted)
            );
        });
    }


    /**
     * Property 7.2: Russian numbers use space as thousand separator.
     * 
     * For any large number, Russian locale SHALL use space
     * as thousand separator (e.g., 1 234 567).
     */
    public function testRussianNumbersUseSpaceSeparator(): void
    {
        $extension = $this->createExtension('ru');
        
        $this->forAll(
            Generator\choose(1000, 9999999)
        )->then(function (int $number) use ($extension): void {
            $formatted = $extension->formatNumber($number);
            
            // Numbers >= 1000 should contain space separator
            if ($number >= 1000) {
                $this->assertStringContainsString(
                    ' ',
                    $formatted,
                    \sprintf('Russian number "%s" should contain space separator for %d', $formatted, $number)
                );
            }
            
            // Should not contain comma as thousand separator
            $this->assertStringNotContainsString(
                ',',
                $formatted,
                \sprintf('Russian number "%s" should not contain comma as thousand separator', $formatted)
            );
        });
    }

    /**
     * Property 7.3: English decimals use period as decimal separator.
     * 
     * For any decimal number, English locale SHALL use period
     * as decimal separator (e.g., 1,234.56).
     */
    public function testEnglishDecimalsUsePeriodSeparator(): void
    {
        $extension = $this->createExtension('en');
        
        $this->forAll(
            Generator\choose(1, 999999),
            Generator\choose(1, 99)
        )->then(function (int $whole, int $decimal) use ($extension): void {
            $number = (float) "$whole.$decimal";
            $formatted = $extension->formatNumber($number, 2);
            
            // Should contain period as decimal separator
            $this->assertStringContainsString(
                '.',
                $formatted,
                \sprintf('English decimal "%s" should contain period separator', $formatted)
            );
        });
    }

    /**
     * Property 7.4: Russian decimals use comma as decimal separator.
     * 
     * For any decimal number, Russian locale SHALL use comma
     * as decimal separator (e.g., 1 234,56).
     */
    public function testRussianDecimalsUseCommaSeparator(): void
    {
        $extension = $this->createExtension('ru');
        
        $this->forAll(
            Generator\choose(1, 999999),
            Generator\choose(1, 99)
        )->then(function (int $whole, int $decimal) use ($extension): void {
            $number = (float) "$whole.$decimal";
            $formatted = $extension->formatNumber($number, 2);
            
            // Should contain comma as decimal separator
            $this->assertStringContainsString(
                ',',
                $formatted,
                \sprintf('Russian decimal "%s" should contain comma as decimal separator', $formatted)
            );
        });
    }


    /**
     * Property 7.5: Formatted numbers preserve numeric value.
     * 
     * For any number, the formatted string SHALL contain
     * all significant digits of the original number.
     */
    public function testFormattedNumbersPreserveValue(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(0, 9999999)
        )->then(function (string $locale, int $number): void {
            $extension = $this->createExtension($locale);
            $formatted = $extension->formatNumber($number);
            
            // Remove separators and compare digits
            $digitsOnly = \preg_replace('/[^0-9]/', '', $formatted);
            
            $this->assertSame(
                (string) $number,
                $digitsOnly,
                \sprintf('Formatted number "%s" should preserve digits of %d', $formatted, $number)
            );
        });
    }

    /**
     * Property 7.6: Null numbers return empty string.
     * 
     * For null number input, formatting SHALL return empty string.
     */
    public function testNullNumbersReturnEmptyString(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(0, 5)
        )->then(function (string $locale, int $decimals): void {
            $extension = $this->createExtension($locale);
            
            $formatted = $extension->formatNumber(null, $decimals);
            
            $this->assertSame(
                '',
                $formatted,
                'Null number should return empty string'
            );
        });
    }

    /**
     * Property 7.7: Zero is formatted correctly.
     * 
     * For zero value, formatting SHALL return "0" with appropriate decimals.
     */
    public function testZeroIsFormattedCorrectly(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(0, 3)
        )->then(function (string $locale, int $decimals): void {
            $extension = $this->createExtension($locale);
            
            $formatted = $extension->formatNumber(0, $decimals);
            
            // Should start with 0
            $this->assertStringStartsWith(
                '0',
                $formatted,
                'Zero should be formatted starting with 0'
            );
            
            // If decimals > 0, should contain decimal separator
            if ($decimals > 0) {
                $separator = $locale === 'ru' ? ',' : '.';
                $this->assertStringContainsString(
                    $separator,
                    $formatted,
                    \sprintf('Zero with decimals should contain decimal separator in locale %s', $locale)
                );
            }
        });
    }

    /**
     * Property 7.8: Negative numbers are formatted correctly.
     * 
     * For negative numbers, formatting SHALL preserve the negative sign.
     */
    public function testNegativeNumbersAreFormattedCorrectly(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(1, 999999)
        )->then(function (string $locale, int $positiveNumber): void {
            $extension = $this->createExtension($locale);
            $negativeNumber = -$positiveNumber;
            
            $formatted = $extension->formatNumber($negativeNumber);
            
            // Should contain minus sign
            $this->assertStringContainsString(
                '-',
                $formatted,
                \sprintf('Negative number "%s" should contain minus sign', $formatted)
            );
        });
    }
}
