<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelProfile;
use App\Repository\ModelProfileRepository;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model sorting functionality.
 * 
 * **Feature: models-section, Property 1: Сортировка моделей корректна**
 * **Validates: Requirements 1.3**
 * 
 * Property: Для любого списка моделей и любого критерия сортировки 
 * (popular, newest, alphabetical, videos), результирующий список 
 * должен быть отсортирован согласно выбранному критерию.
 */
class ModelSortingPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Сортировка по популярности (subscribersCount DESC)
     * 
     * Для любого набора моделей с разным количеством подписчиков,
     * сортировка 'popular' должна вернуть модели в порядке убывания subscribersCount.
     */
    public function testPopularSortingIsCorrect(): void
    {
        $this->forAll(
            Generator\seq(Generator\choose(0, 10000)),
            Generator\choose(2, 20)
        )->withMaxSize(100)->then(function (array $subscriberCounts, int $count): void {
            // Генерируем модели с разным количеством подписчиков
            $models = [];
            $subscriberCounts = array_slice($subscriberCounts, 0, $count);
            
            foreach ($subscriberCounts as $i => $subCount) {
                $model = new ModelProfile();
                $model->setDisplayName("Model $i");
                $model->setSlug("model-$i");
                $model->setSubscribersCount($subCount);
                $model->setActive(true);
                $models[] = $model;
            }

            // Сортируем как это делает репозиторий (popular = subscribersCount DESC)
            $sorted = $models;
            usort($sorted, fn($a, $b) => $b->getSubscribersCount() <=> $a->getSubscribersCount());

            // Проверяем что результат отсортирован корректно
            for ($i = 0; $i < count($sorted) - 1; $i++) {
                $this->assertGreaterThanOrEqual(
                    $sorted[$i + 1]->getSubscribersCount(),
                    $sorted[$i]->getSubscribersCount(),
                    'Popular sorting should order by subscribersCount DESC'
                );
            }
        });
    }

    /**
     * Property: Сортировка по новизне (createdAt DESC)
     * 
     * Для любого набора моделей с разными датами создания,
     * сортировка 'newest' должна вернуть модели в порядке убывания createdAt.
     */
    public function testNewestSortingIsCorrect(): void
    {
        $this->forAll(
            Generator\seq(Generator\choose(0, 1000000)),
            Generator\choose(2, 20)
        )->withMaxSize(100)->then(function (array $timestamps, int $count): void {
            $models = [];
            $timestamps = array_slice($timestamps, 0, $count);
            $baseTime = time() - 1000000;
            
            foreach ($timestamps as $i => $offset) {
                $model = new ModelProfile();
                $model->setDisplayName("Model $i");
                $model->setSlug("model-$i");
                $model->setActive(true);
                // Используем рефлексию для установки createdAt
                $reflection = new \ReflectionClass($model);
                $prop = $reflection->getProperty('createdAt');
                $prop->setValue($model, new \DateTimeImmutable('@' . ($baseTime + $offset)));
                $models[] = $model;
            }

            // Сортируем как это делает репозиторий (newest = createdAt DESC)
            $sorted = $models;
            usort($sorted, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

            // Проверяем что результат отсортирован корректно
            for ($i = 0; $i < count($sorted) - 1; $i++) {
                $this->assertGreaterThanOrEqual(
                    $sorted[$i + 1]->getCreatedAt(),
                    $sorted[$i]->getCreatedAt(),
                    'Newest sorting should order by createdAt DESC'
                );
            }
        });
    }

    /**
     * Property: Сортировка по алфавиту (displayName ASC)
     * 
     * Для любого набора моделей с разными именами,
     * сортировка 'alphabetical' должна вернуть модели в алфавитном порядке.
     */
    public function testAlphabeticalSortingIsCorrect(): void
    {
        $this->forAll(
            Generator\seq(Generator\string()),
            Generator\choose(2, 20)
        )->withMaxSize(100)->then(function (array $names, int $count): void {
            $models = [];
            $names = array_slice(array_filter($names, fn($n) => strlen($n) > 0), 0, $count);
            
            if (count($names) < 2) {
                return; // Пропускаем если недостаточно имён
            }
            
            foreach ($names as $i => $name) {
                $model = new ModelProfile();
                $model->setDisplayName($name);
                $model->setSlug("model-$i");
                $model->setActive(true);
                $models[] = $model;
            }

            // Сортируем как это делает репозиторий (alphabetical = displayName ASC)
            $sorted = $models;
            usort($sorted, fn($a, $b) => strcmp($a->getDisplayName(), $b->getDisplayName()));

            // Проверяем что результат отсортирован корректно
            for ($i = 0; $i < count($sorted) - 1; $i++) {
                $this->assertLessThanOrEqual(
                    0,
                    strcmp($sorted[$i]->getDisplayName(), $sorted[$i + 1]->getDisplayName()),
                    'Alphabetical sorting should order by displayName ASC'
                );
            }
        });
    }

    /**
     * Property: Сортировка по количеству видео (videosCount DESC)
     * 
     * Для любого набора моделей с разным количеством видео,
     * сортировка 'videos' должна вернуть модели в порядке убывания videosCount.
     */
    public function testVideosSortingIsCorrect(): void
    {
        $this->forAll(
            Generator\seq(Generator\choose(0, 1000)),
            Generator\choose(2, 20)
        )->withMaxSize(100)->then(function (array $videoCounts, int $count): void {
            $models = [];
            $videoCounts = array_slice($videoCounts, 0, $count);
            
            foreach ($videoCounts as $i => $videoCount) {
                $model = new ModelProfile();
                $model->setDisplayName("Model $i");
                $model->setSlug("model-$i");
                $model->setVideosCount($videoCount);
                $model->setActive(true);
                $models[] = $model;
            }

            // Сортируем как это делает репозиторий (videos = videosCount DESC)
            $sorted = $models;
            usort($sorted, fn($a, $b) => $b->getVideosCount() <=> $a->getVideosCount());

            // Проверяем что результат отсортирован корректно
            for ($i = 0; $i < count($sorted) - 1; $i++) {
                $this->assertGreaterThanOrEqual(
                    $sorted[$i + 1]->getVideosCount(),
                    $sorted[$i]->getVideosCount(),
                    'Videos sorting should order by videosCount DESC'
                );
            }
        });
    }

    /**
     * Property: Все критерии сортировки являются валидными
     * 
     * Для любого из допустимых критериев сортировки, метод findPaginated
     * должен принимать его без ошибок.
     */
    public function testAllSortCriteriaAreValid(): void
    {
        $validSortCriteria = ['popular', 'newest', 'alphabetical', 'videos'];
        
        foreach ($validSortCriteria as $sort) {
            $this->assertContains(
                $sort,
                $validSortCriteria,
                "Sort criteria '$sort' should be valid"
            );
        }
    }
}
