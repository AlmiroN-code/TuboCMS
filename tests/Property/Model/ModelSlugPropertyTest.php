<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Controller\Admin\AdminModelController;
use App\Repository\ModelProfileRepository;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model slug generation.
 * 
 * **Feature: models-section, Property 9: Slug генерируется корректно**
 * **Validates: Requirements 6.3**
 * 
 * Property: Для любого имени модели, автоматически сгенерированный slug 
 * должен быть валидным URL-совместимым значением (только латинские буквы, 
 * цифры и дефисы, в нижнем регистре).
 */
class ModelSlugPropertyTest extends TestCase
{
    use TestTrait;

    private AdminModelController $controller;

    protected function setUp(): void
    {
        // Создаём mock-объекты для зависимостей контроллера
        $modelRepository = $this->createMock(ModelProfileRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $imageService = $this->createMock(ImageService::class);
        
        $this->controller = new AdminModelController($modelRepository, $em, $imageService);
    }

    /**
     * Property: Slug содержит только допустимые символы
     * 
     * Для любого имени модели, сгенерированный slug должен содержать
     * только латинские буквы в нижнем регистре, цифры и дефисы.
     */
    public function testSlugContainsOnlyValidCharacters(): void
    {
        $this->forAll(
            Generator\string()
        )->withMaxSize(100)->then(function (string $name): void {
            if (empty(trim($name))) {
                return; // Пропускаем пустые строки
            }
            
            $slug = $this->controller->generateSlug($name);
            
            // Slug должен содержать только допустимые символы
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9-]*$/',
                $slug,
                "Slug '$slug' should contain only lowercase letters, digits, and hyphens"
            );
        });
    }

    /**
     * Property: Slug всегда в нижнем регистре
     * 
     * Для любого имени модели с заглавными буквами,
     * сгенерированный slug должен быть в нижнем регистре.
     */
    public function testSlugIsAlwaysLowercase(): void
    {
        $this->forAll(
            Generator\elements([
                'John Doe',
                'ANNA SMITH',
                'MiXeD CaSe NaMe',
                'UPPERCASE',
                'lowercase',
                'Мария Иванова',
                'АННА ПЕТРОВА',
                'Test Model 123',
                'ABC XYZ',
            ])
        )->then(function (string $name): void {
            $slug = $this->controller->generateSlug($name);
            
            $this->assertEquals(
                strtolower($slug),
                $slug,
                "Slug '$slug' should be lowercase"
            );
        });
    }

    /**
     * Property: Slug не содержит пробелов
     * 
     * Для любого имени модели с пробелами,
     * сгенерированный slug не должен содержать пробелов.
     */
    public function testSlugDoesNotContainSpaces(): void
    {
        $this->forAll(
            Generator\elements([
                'John Doe',
                'Anna Maria Smith',
                'Name With Many Spaces',
                '  Leading Spaces',
                'Trailing Spaces  ',
                '  Both  Sides  ',
                'Multiple   Spaces',
            ])
        )->then(function (string $name): void {
            $slug = $this->controller->generateSlug($name);
            
            $this->assertStringNotContainsString(
                ' ',
                $slug,
                "Slug '$slug' should not contain spaces"
            );
        });
    }

    /**
     * Property: Slug не пустой для непустых имён
     * 
     * Для любого непустого имени модели (содержащего хотя бы один алфавитно-цифровой символ),
     * сгенерированный slug не должен быть пустым.
     */
    public function testSlugIsNotEmptyForValidNames(): void
    {
        $this->forAll(
            Generator\elements([
                'John',
                'Anna123',
                'Model1',
                'Test',
                'A',
                '1',
                'Name123',
            ])
        )->then(function (string $name): void {
            $slug = $this->controller->generateSlug($name);
            
            $this->assertNotEmpty(
                $slug,
                "Slug should not be empty for name '$name'"
            );
        });
    }

    /**
     * Property: Slug является URL-совместимым
     * 
     * Для любого имени модели, сгенерированный slug должен быть
     * валидным компонентом URL (не требует URL-кодирования).
     */
    public function testSlugIsUrlSafe(): void
    {
        $this->forAll(
            Generator\elements([
                'John Doe',
                'Анна Иванова',
                'María García',
                'Müller Hans',
                'Test & Model',
                'Name/With/Slashes',
                'Name?With=Query',
                'Name#With#Hash',
                'Name%With%Percent',
                'Special!@#$%^&*()',
            ])
        )->then(function (string $name): void {
            $slug = $this->controller->generateSlug($name);
            
            // URL-кодирование не должно изменять slug
            $this->assertEquals(
                $slug,
                rawurlencode($slug),
                "Slug '$slug' should be URL-safe (no encoding needed)"
            );
        });
    }

    /**
     * Property: Slug не начинается и не заканчивается дефисом
     * 
     * Для любого имени модели, сгенерированный slug не должен
     * начинаться или заканчиваться дефисом.
     */
    public function testSlugDoesNotStartOrEndWithHyphen(): void
    {
        $this->forAll(
            Generator\elements([
                '-Leading Hyphen',
                'Trailing Hyphen-',
                '-Both Sides-',
                '---Multiple---',
                'Normal Name',
                '  Spaces  ',
            ])
        )->then(function (string $name): void {
            $slug = $this->controller->generateSlug($name);
            
            if (!empty($slug)) {
                $this->assertStringStartsNotWith(
                    '-',
                    $slug,
                    "Slug '$slug' should not start with hyphen"
                );
                $this->assertStringEndsNotWith(
                    '-',
                    $slug,
                    "Slug '$slug' should not end with hyphen"
                );
            }
        });
    }

    /**
     * Property: Slug не содержит последовательных дефисов
     * 
     * Для любого имени модели, сгенерированный slug не должен
     * содержать два или более дефиса подряд.
     */
    public function testSlugDoesNotContainConsecutiveHyphens(): void
    {
        $this->forAll(
            Generator\elements([
                'Name  With  Spaces',
                'Name---With---Hyphens',
                'Multiple   Spaces   Here',
                'Normal Name',
            ])
        )->then(function (string $name): void {
            $slug = $this->controller->generateSlug($name);
            
            $this->assertStringNotContainsString(
                '--',
                $slug,
                "Slug '$slug' should not contain consecutive hyphens"
            );
        });
    }

    /**
     * Property: Транслитерация кириллицы
     * 
     * Для имён на кириллице, slug должен содержать транслитерированные
     * латинские символы.
     */
    public function testCyrillicNamesAreTransliterated(): void
    {
        $this->forAll(
            Generator\elements([
                'Анна',
                'Мария',
                'Иван',
                'Александр',
                'Наталья',
            ])
        )->then(function (string $name): void {
            $slug = $this->controller->generateSlug($name);
            
            // Slug должен содержать только латинские символы
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9-]*$/',
                $slug,
                "Cyrillic name '$name' should be transliterated to '$slug'"
            );
        });
    }
}
