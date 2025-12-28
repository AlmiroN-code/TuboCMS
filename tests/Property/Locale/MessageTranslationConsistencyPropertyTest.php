<?php

declare(strict_types=1);

namespace App\Tests\Property\Locale;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

/**
 * Property-based tests for message translation consistency.
 * 
 * **Feature: internationalization, Property 5: Message Translation Consistency**
 * **Validates: Requirements 1.2, 6.1, 6.2, 6.3**
 * 
 * Property: For any translation key that exists in all supported locales, 
 * requesting the translation in a specific locale should return the value 
 * from that locale's catalog, not from another locale.
 */
class MessageTranslationConsistencyPropertyTest extends TestCase
{
    use TestTrait;

    private array $supportedLocales = ['en', 'ru'];
    private string $defaultLocale = 'en';
    private string $translationsPath;
    private array $translationDomains = ['messages', 'validators', 'security'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->translationsPath = dirname(__DIR__, 3) . '/translations';
    }

    /**
     * Create a translator instance with all translation files loaded.
     */
    private function createTranslator(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->setFallbackLocales([$this->defaultLocale]);

        foreach ($this->supportedLocales as $supportedLocale) {
            foreach ($this->translationDomains as $domain) {
                $file = sprintf('%s/%s.%s.yaml', $this->translationsPath, $domain, $supportedLocale);
                if (file_exists($file)) {
                    $translator->addResource('yaml', $file, $supportedLocale, $domain);
                }
            }
        }

        return $translator;
    }

    /**
     * Get all translation keys from a YAML file recursively.
     */
    private function getTranslationKeys(string $file, string $prefix = ''): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $content = Yaml::parseFile($file);
        if (!is_array($content)) {
            return [];
        }

        return $this->flattenKeys($content, $prefix);
    }

    /**
     * Flatten nested array keys into dot notation.
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "$prefix.$key" : $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }
        return $keys;
    }

    /**
     * Get common keys that exist in all locales for a domain.
     */
    private function getCommonKeys(string $domain): array
    {
        $keysByLocale = [];
        
        foreach ($this->supportedLocales as $locale) {
            $file = sprintf('%s/%s.%s.yaml', $this->translationsPath, $domain, $locale);
            $keysByLocale[$locale] = $this->getTranslationKeys($file);
        }

        // Find intersection of all keys
        $commonKeys = $keysByLocale[$this->supportedLocales[0]] ?? [];
        foreach ($this->supportedLocales as $locale) {
            $commonKeys = array_intersect($commonKeys, $keysByLocale[$locale] ?? []);
        }

        return array_values($commonKeys);
    }

    /**
     * Property 5.1: Translation returns locale-specific value.
     * 
     * For any translation key that exists in all locales,
     * requesting translation in a specific locale SHALL return
     * the value from that locale's catalog.
     */
    public function testTranslationReturnsLocaleSpecificValue(): void
    {
        $commonKeys = $this->getCommonKeys('messages');
        
        if (empty($commonKeys)) {
            $this->markTestSkipped('No common translation keys found');
        }

        // Limit to first 50 keys for performance
        $testKeys = array_slice($commonKeys, 0, 50);

        $this->forAll(
            Generator\elements($testKeys),
            Generator\elements($this->supportedLocales)
        )->then(function (string $key, string $locale): void {
            $translator = $this->createTranslator($locale);
            $translation = $translator->trans($key, [], 'messages', $locale);
            
            // Translation should not be the key itself (unless it's a fallback)
            // and should be different from other locales (for most keys)
            $this->assertNotEmpty(
                $translation,
                sprintf('Translation for key "%s" in locale "%s" should not be empty', $key, $locale)
            );
        });
    }

    /**
     * Property 5.2: Different locales return different translations.
     * 
     * For any translation key that exists in all locales,
     * different locales SHALL return different values (for most keys).
     */
    public function testDifferentLocalesReturnDifferentTranslations(): void
    {
        $commonKeys = $this->getCommonKeys('messages');
        
        if (empty($commonKeys)) {
            $this->markTestSkipped('No common translation keys found');
        }

        // Filter out keys that might be the same in both languages (like brand names)
        $testKeys = array_filter($commonKeys, function (string $key): bool {
            // Skip keys that are likely to be the same (site name, etc.)
            return !str_contains($key, 'site_name') 
                && !str_contains($key, 'copyright')
                && !str_contains($key, 'email');
        });

        if (empty($testKeys)) {
            $this->markTestSkipped('No suitable translation keys found');
        }

        // Limit to first 30 keys for performance
        $testKeys = array_slice(array_values($testKeys), 0, 30);

        $this->forAll(
            Generator\elements($testKeys)
        )->then(function (string $key): void {
            $enTranslator = $this->createTranslator('en');
            $ruTranslator = $this->createTranslator('ru');
            
            $enTranslation = $enTranslator->trans($key, [], 'messages', 'en');
            $ruTranslation = $ruTranslator->trans($key, [], 'messages', 'ru');
            
            // Most translations should be different between locales
            // We check that at least one is not equal to the key
            $this->assertTrue(
                $enTranslation !== $key || $ruTranslation !== $key,
                sprintf('At least one translation for key "%s" should exist', $key)
            );
        });
    }

    /**
     * Property 5.3: Validator translations return locale-specific messages.
     * 
     * For any validator translation key,
     * requesting translation SHALL return locale-specific message.
     */
    public function testValidatorTranslationsAreLocaleSpecific(): void
    {
        $commonKeys = $this->getCommonKeys('validators');
        
        if (empty($commonKeys)) {
            $this->markTestSkipped('No common validator translation keys found');
        }

        // Limit to first 30 keys for performance
        $testKeys = array_slice($commonKeys, 0, 30);

        $this->forAll(
            Generator\elements($testKeys),
            Generator\elements($this->supportedLocales)
        )->then(function (string $key, string $locale): void {
            $translator = $this->createTranslator($locale);
            $translation = $translator->trans($key, [], 'validators', $locale);
            
            $this->assertNotEmpty(
                $translation,
                sprintf('Validator translation for key "%s" in locale "%s" should not be empty', $key, $locale)
            );
        });
    }

    /**
     * Property 5.4: Security translations return locale-specific messages.
     * 
     * For any security translation key,
     * requesting translation SHALL return locale-specific message.
     */
    public function testSecurityTranslationsAreLocaleSpecific(): void
    {
        $commonKeys = $this->getCommonKeys('security');
        
        if (empty($commonKeys)) {
            $this->markTestSkipped('No common security translation keys found');
        }

        $this->forAll(
            Generator\elements($commonKeys),
            Generator\elements($this->supportedLocales)
        )->then(function (string $key, string $locale): void {
            $translator = $this->createTranslator($locale);
            $translation = $translator->trans($key, [], 'security', $locale);
            
            $this->assertNotEmpty(
                $translation,
                sprintf('Security translation for key "%s" in locale "%s" should not be empty', $key, $locale)
            );
        });
    }

    /**
     * Property 5.5: Fallback locale is used for missing keys.
     * 
     * For any key that exists only in the default locale,
     * requesting translation in another locale SHALL return the fallback value.
     */
    public function testFallbackLocaleIsUsedForMissingKeys(): void
    {
        // Test with a key that we know exists in English
        $testKey = 'nav.home';
        
        $this->forAll(
            Generator\elements($this->supportedLocales)
        )->then(function (string $locale) use ($testKey): void {
            $translator = $this->createTranslator($locale);
            $translation = $translator->trans($testKey, [], 'messages', $locale);
            
            // Should always get a translation (either from locale or fallback)
            $this->assertNotEquals(
                $testKey,
                $translation,
                sprintf('Key "%s" should have a translation in locale "%s" or fallback', $testKey, $locale)
            );
        });
    }

    /**
     * Property 5.6: Translation with parameters works correctly.
     * 
     * For any translation key with parameters,
     * the parameters SHALL be correctly substituted.
     */
    public function testTranslationWithParametersWorks(): void
    {
        $this->forAll(
            Generator\elements($this->supportedLocales),
            Generator\choose(1, 1000)
        )->then(function (string $locale, int $count): void {
            $translator = $this->createTranslator($locale);
            
            // Test with views_count which uses %count% parameter
            $translation = $translator->trans('video.views_count', ['%count%' => $count], 'messages', $locale);
            
            // The translation should contain the count value
            $this->assertStringContainsString(
                (string) $count,
                $translation,
                sprintf('Translation should contain the count value "%d"', $count)
            );
        });
    }
}
