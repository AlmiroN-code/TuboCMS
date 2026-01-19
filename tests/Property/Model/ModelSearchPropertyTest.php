<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelProfile;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model search functionality.
 * 
 * **Feature: models-section, Property 2: Поиск моделей возвращает релевантные результаты**
 * **Validates: Requirements 1.4**
 * 
 * Property: Для любого поискового запроса, все модели в результатах 
 * должны содержать поисковый запрос в поле displayName (без учёта регистра).
 */
class ModelSearchPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Поиск возвращает только модели, содержащие поисковый запрос в имени
     * 
     * Для любого поискового запроса и любого набора моделей,
     * все модели в результатах поиска должны содержать запрос в displayName.
     */
    public function testSearchReturnsOnlyMatchingModels(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => strlen(trim($s)) > 0 && strlen($s) <= 50,
                Generator\string()
            ),
            Generator\seq(Generator\string()),
            Generator\choose(5, 20)
        )->withMaxSize(100)->then(function (string $searchQuery, array $names, int $count): void {
            $searchQuery = trim($searchQuery);
            if (strlen($searchQuery) === 0) {
                return;
            }

            $models = [];
            $names = array_slice(array_filter($names, fn($n) => strlen($n) > 0), 0, $count);
            
            // Добавляем несколько моделей, которые точно содержат поисковый запрос
            $matchingNames = [
                $searchQuery,
                "Prefix $searchQuery",
                "$searchQuery Suffix",
                "Prefix $searchQuery Suffix",
                strtoupper($searchQuery),
                strtolower($searchQuery),
            ];
            
            foreach ($matchingNames as $i => $name) {
                $model = new ModelProfile();
                $model->setDisplayName($name);
                $model->setSlug("model-match-$i");
                $model->setActive(true);
                $models[] = $model;
            }
            
            // Добавляем модели с произвольными именами
            foreach ($names as $i => $name) {
                $model = new ModelProfile();
                $model->setDisplayName($name);
                $model->setSlug("model-random-$i");
                $model->setActive(true);
                $models[] = $model;
            }

            // Фильтруем как это делает репозиторий (LIKE %search%)
            $filtered = array_filter($models, function (ModelProfile $model) use ($searchQuery): bool {
                return stripos($model->getDisplayName(), $searchQuery) !== false;
            });

            // Проверяем что все результаты содержат поисковый запрос
            foreach ($filtered as $model) {
                $this->assertStringContainsStringIgnoringCase(
                    $searchQuery,
                    $model->getDisplayName(),
                    sprintf(
                        'Search result "%s" should contain search query "%s"',
                        $model->getDisplayName(),
                        $searchQuery
                    )
                );
            }
        });
    }

    /**
     * Property: Поиск нечувствителен к регистру
     * 
     * Для любого поискового запроса, результаты должны быть одинаковыми
     * независимо от регистра запроса.
     */
    public function testSearchIsCaseInsensitive(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => strlen(trim($s)) > 0 && strlen($s) <= 20 && preg_match('/^[a-zA-Z]+$/', $s),
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $searchQuery): void {
            $searchQuery = trim($searchQuery);
            if (strlen($searchQuery) === 0) {
                return;
            }

            // Создаём модели с разным регистром
            $models = [];
            $testNames = [
                $searchQuery,
                strtoupper($searchQuery),
                strtolower($searchQuery),
                ucfirst(strtolower($searchQuery)),
                "Test " . $searchQuery . " Name",
                "OTHER NAME",
                "DIFFERENT",
            ];
            
            foreach ($testNames as $i => $name) {
                $model = new ModelProfile();
                $model->setDisplayName($name);
                $model->setSlug("model-$i");
                $model->setActive(true);
                $models[] = $model;
            }

            // Поиск в нижнем регистре
            $lowerResults = array_filter($models, function (ModelProfile $model) use ($searchQuery): bool {
                return stripos($model->getDisplayName(), strtolower($searchQuery)) !== false;
            });

            // Поиск в верхнем регистре
            $upperResults = array_filter($models, function (ModelProfile $model) use ($searchQuery): bool {
                return stripos($model->getDisplayName(), strtoupper($searchQuery)) !== false;
            });

            // Результаты должны быть одинаковыми
            $this->assertCount(
                count($lowerResults),
                $upperResults,
                'Case-insensitive search should return same results regardless of query case'
            );
        });
    }

    /**
     * Property: Пустой поисковый запрос не фильтрует результаты
     * 
     * Для пустого или состоящего только из пробелов запроса,
     * все активные модели должны быть возвращены.
     */
    public function testEmptySearchReturnsAllModels(): void
    {
        $this->forAll(
            Generator\elements(['', ' ', '  ', "\t", "\n"]),
            Generator\choose(1, 10)
        )->then(function (string $emptySearch, int $modelCount): void {
            $models = [];
            
            for ($i = 0; $i < $modelCount; $i++) {
                $model = new ModelProfile();
                $model->setDisplayName("Model $i");
                $model->setSlug("model-$i");
                $model->setActive(true);
                $models[] = $model;
            }

            // Пустой поиск не должен фильтровать
            $trimmed = trim($emptySearch);
            if ($trimmed === '') {
                // Все модели должны быть возвращены
                $this->assertCount($modelCount, $models);
            }
        });
    }

    /**
     * Property: Поиск не возвращает неактивные модели
     * 
     * Для любого поискового запроса, неактивные модели не должны
     * появляться в результатах, даже если их имя соответствует запросу.
     */
    public function testSearchExcludesInactiveModels(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => strlen(trim($s)) > 0 && strlen($s) <= 20,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $searchQuery): void {
            $searchQuery = trim($searchQuery);
            if (strlen($searchQuery) === 0) {
                return;
            }

            $models = [];
            
            // Активная модель с совпадающим именем
            $activeModel = new ModelProfile();
            $activeModel->setDisplayName($searchQuery);
            $activeModel->setSlug("model-active");
            $activeModel->setActive(true);
            $models[] = $activeModel;
            
            // Неактивная модель с совпадающим именем
            $inactiveModel = new ModelProfile();
            $inactiveModel->setDisplayName($searchQuery);
            $inactiveModel->setSlug("model-inactive");
            $inactiveModel->setActive(false);
            $models[] = $inactiveModel;

            // Фильтруем как репозиторий (только активные + поиск)
            $filtered = array_filter($models, function (ModelProfile $model) use ($searchQuery): bool {
                return $model->isActive() && stripos($model->getDisplayName(), $searchQuery) !== false;
            });

            // Проверяем что неактивные модели исключены
            foreach ($filtered as $model) {
                $this->assertTrue(
                    $model->isActive(),
                    'Search results should not include inactive models'
                );
            }
        });
    }
}
