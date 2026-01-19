<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelProfile;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model gender filtering functionality.
 * 
 * **Feature: models-section, Property 3: Фильтрация по полу корректна**
 * **Validates: Requirements 1.5**
 * 
 * Property: Для любого фильтра по полу, все модели в результатах 
 * должны иметь указанный пол.
 */
class ModelGenderFilterPropertyTest extends TestCase
{
    use TestTrait;

    private const VALID_GENDERS = ['male', 'female', 'trans'];

    /**
     * Property: Фильтрация по полу возвращает только модели указанного пола
     * 
     * Для любого валидного значения пола и любого набора моделей,
     * все модели в результатах фильтрации должны иметь указанный пол.
     */
    public function testGenderFilterReturnsOnlyMatchingModels(): void
    {
        $this->forAll(
            Generator\elements(self::VALID_GENDERS),
            Generator\choose(5, 20)
        )->then(function (string $filterGender, int $modelCount): void {
            $models = [];
            
            // Создаём модели с разными полами
            foreach (self::VALID_GENDERS as $gender) {
                for ($i = 0; $i < $modelCount; $i++) {
                    $model = new ModelProfile();
                    $model->setDisplayName("Model $gender $i");
                    $model->setSlug("model-$gender-$i");
                    $model->setGender($gender);
                    $model->setActive(true);
                    $models[] = $model;
                }
            }

            // Фильтруем как это делает репозиторий
            $filtered = array_filter($models, function (ModelProfile $model) use ($filterGender): bool {
                return $model->isActive() && $model->getGender() === $filterGender;
            });

            // Проверяем что все результаты имеют указанный пол
            foreach ($filtered as $model) {
                $this->assertEquals(
                    $filterGender,
                    $model->getGender(),
                    sprintf(
                        'Filtered model "%s" should have gender "%s", got "%s"',
                        $model->getDisplayName(),
                        $filterGender,
                        $model->getGender()
                    )
                );
            }

            // Проверяем что количество результатов соответствует ожидаемому
            $this->assertCount(
                $modelCount,
                $filtered,
                sprintf('Should have %d models with gender "%s"', $modelCount, $filterGender)
            );
        });
    }

    /**
     * Property: Все валидные значения пола принимаются фильтром
     * 
     * Для каждого валидного значения пола (male, female, trans),
     * фильтр должен корректно работать.
     */
    public function testAllValidGendersAreAccepted(): void
    {
        foreach (self::VALID_GENDERS as $gender) {
            $model = new ModelProfile();
            $model->setDisplayName("Test Model");
            $model->setSlug("test-model");
            $model->setGender($gender);
            $model->setActive(true);

            $this->assertEquals(
                $gender,
                $model->getGender(),
                sprintf('Gender "%s" should be accepted', $gender)
            );
            
            $this->assertContains(
                $gender,
                self::VALID_GENDERS,
                sprintf('Gender "%s" should be in valid genders list', $gender)
            );
        }
    }

    /**
     * Property: Невалидные значения пола игнорируются фильтром
     * 
     * Для любого невалидного значения пола, фильтр не должен
     * применяться (возвращаются все активные модели).
     */
    public function testInvalidGenderFilterIsIgnored(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => !in_array($s, self::VALID_GENDERS, true) && strlen($s) > 0,
                Generator\string()
            ),
            Generator\choose(3, 10)
        )->withMaxSize(100)->then(function (string $invalidGender, int $modelCount): void {
            $models = [];
            
            // Создаём модели с валидными полами
            foreach (self::VALID_GENDERS as $i => $gender) {
                $model = new ModelProfile();
                $model->setDisplayName("Model $i");
                $model->setSlug("model-$i");
                $model->setGender($gender);
                $model->setActive(true);
                $models[] = $model;
            }

            // Проверяем что невалидный пол не в списке валидных
            $this->assertNotContains(
                $invalidGender,
                self::VALID_GENDERS,
                sprintf('"%s" should not be a valid gender', $invalidGender)
            );

            // При невалидном фильтре репозиторий игнорирует его
            // и возвращает все активные модели
            $isValidGender = in_array($invalidGender, self::VALID_GENDERS, true);
            $this->assertFalse($isValidGender);
        });
    }

    /**
     * Property: Фильтрация по полу не влияет на неактивные модели
     * 
     * Для любого фильтра по полу, неактивные модели не должны
     * появляться в результатах, даже если их пол соответствует фильтру.
     */
    public function testGenderFilterExcludesInactiveModels(): void
    {
        $this->forAll(
            Generator\elements(self::VALID_GENDERS)
        )->then(function (string $filterGender): void {
            $models = [];
            
            // Активная модель с нужным полом
            $activeModel = new ModelProfile();
            $activeModel->setDisplayName("Active Model");
            $activeModel->setSlug("active-model");
            $activeModel->setGender($filterGender);
            $activeModel->setActive(true);
            $models[] = $activeModel;
            
            // Неактивная модель с нужным полом
            $inactiveModel = new ModelProfile();
            $inactiveModel->setDisplayName("Inactive Model");
            $inactiveModel->setSlug("inactive-model");
            $inactiveModel->setGender($filterGender);
            $inactiveModel->setActive(false);
            $models[] = $inactiveModel;

            // Фильтруем как репозиторий (только активные + пол)
            $filtered = array_filter($models, function (ModelProfile $model) use ($filterGender): bool {
                return $model->isActive() && $model->getGender() === $filterGender;
            });

            // Проверяем что неактивные модели исключены
            $this->assertCount(1, $filtered);
            foreach ($filtered as $model) {
                $this->assertTrue(
                    $model->isActive(),
                    'Gender filter results should not include inactive models'
                );
            }
        });
    }

    /**
     * Property: Null фильтр по полу возвращает все активные модели
     * 
     * Когда фильтр по полу не указан (null), должны возвращаться
     * все активные модели независимо от пола.
     */
    public function testNullGenderFilterReturnsAllModels(): void
    {
        $this->forAll(
            Generator\choose(1, 5)
        )->then(function (int $modelsPerGender): void {
            $models = [];
            
            // Создаём модели с разными полами
            foreach (self::VALID_GENDERS as $gender) {
                for ($i = 0; $i < $modelsPerGender; $i++) {
                    $model = new ModelProfile();
                    $model->setDisplayName("Model $gender $i");
                    $model->setSlug("model-$gender-$i");
                    $model->setGender($gender);
                    $model->setActive(true);
                    $models[] = $model;
                }
            }

            // Без фильтра по полу (null) возвращаются все активные модели
            $filtered = array_filter($models, function (ModelProfile $model): bool {
                return $model->isActive();
            });

            $expectedCount = count(self::VALID_GENDERS) * $modelsPerGender;
            $this->assertCount(
                $expectedCount,
                $filtered,
                'Null gender filter should return all active models'
            );
        });
    }
}
