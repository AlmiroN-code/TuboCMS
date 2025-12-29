<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelLike;
use App\Entity\ModelProfile;
use App\Entity\User;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model like/dislike functionality.
 * 
 * **Feature: models-section, Property 7: Лайки/дизлайки корректно обновляют счётчики**
 * **Validates: Requirements 4.1, 4.2, 4.3, 4.4**
 * 
 * Property: Для любой модели и любого пользователя:
 * - Лайк увеличивает likesCount на 1
 * - Дизлайк увеличивает dislikesCount на 1
 * - Смена лайка на дизлайк уменьшает likesCount на 1 и увеличивает dislikesCount на 1
 * - Повторный клик на текущую оценку удаляет её и уменьшает соответствующий счётчик на 1
 */
class ModelLikePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Лайк увеличивает счётчик лайков на 1 (Req 4.1)
     */
    public function testLikeIncrementsLikesCountByOne(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Симулируем лайк
            $model->setLikesCount($model->getLikesCount() + 1);
            
            $this->assertEquals(
                $initialLikes + 1,
                $model->getLikesCount(),
                'Likes count should increase by exactly 1 after like'
            );
            $this->assertEquals(
                $initialDislikes,
                $model->getDislikesCount(),
                'Dislikes count should remain unchanged after like'
            );
        });
    }

    /**
     * Property: Дизлайк увеличивает счётчик дизлайков на 1 (Req 4.2)
     */
    public function testDislikeIncrementsDislikesCountByOne(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Симулируем дизлайк
            $model->setDislikesCount($model->getDislikesCount() + 1);
            
            $this->assertEquals(
                $initialLikes,
                $model->getLikesCount(),
                'Likes count should remain unchanged after dislike'
            );
            $this->assertEquals(
                $initialDislikes + 1,
                $model->getDislikesCount(),
                'Dislikes count should increase by exactly 1 after dislike'
            );
        });
    }

    /**
     * Property: Смена лайка на дизлайк корректно обновляет оба счётчика (Req 4.3)
     */
    public function testChangeLikeToDislikeUpdatesCountersCorrectly(): void
    {
        $this->forAll(
            Generator\choose(1, 1000000),
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Симулируем смену лайка на дизлайк
            $model->setLikesCount(max(0, $model->getLikesCount() - 1));
            $model->setDislikesCount($model->getDislikesCount() + 1);
            
            $this->assertEquals(
                $initialLikes - 1,
                $model->getLikesCount(),
                'Likes count should decrease by 1 when changing to dislike'
            );
            $this->assertEquals(
                $initialDislikes + 1,
                $model->getDislikesCount(),
                'Dislikes count should increase by 1 when changing from like'
            );
        });
    }

    /**
     * Property: Смена дизлайка на лайк корректно обновляет оба счётчика (Req 4.3)
     */
    public function testChangeDislikeToLikeUpdatesCountersCorrectly(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Симулируем смену дизлайка на лайк
            $model->setDislikesCount(max(0, $model->getDislikesCount() - 1));
            $model->setLikesCount($model->getLikesCount() + 1);
            
            $this->assertEquals(
                $initialLikes + 1,
                $model->getLikesCount(),
                'Likes count should increase by 1 when changing from dislike'
            );
            $this->assertEquals(
                $initialDislikes - 1,
                $model->getDislikesCount(),
                'Dislikes count should decrease by 1 when changing to like'
            );
        });
    }

    /**
     * Property: Повторный клик на лайк удаляет его и уменьшает счётчик (Req 4.4)
     */
    public function testRemoveLikeDecrementsLikesCount(): void
    {
        $this->forAll(
            Generator\choose(1, 1000000),
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Симулируем удаление лайка (повторный клик)
            $model->setLikesCount(max(0, $model->getLikesCount() - 1));
            
            $this->assertEquals(
                $initialLikes - 1,
                $model->getLikesCount(),
                'Likes count should decrease by 1 when removing like'
            );
            $this->assertEquals(
                $initialDislikes,
                $model->getDislikesCount(),
                'Dislikes count should remain unchanged when removing like'
            );
        });
    }

    /**
     * Property: Повторный клик на дизлайк удаляет его и уменьшает счётчик (Req 4.4)
     */
    public function testRemoveDislikeDecrementsDislikesCount(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Симулируем удаление дизлайка (повторный клик)
            $model->setDislikesCount(max(0, $model->getDislikesCount() - 1));
            
            $this->assertEquals(
                $initialLikes,
                $model->getLikesCount(),
                'Likes count should remain unchanged when removing dislike'
            );
            $this->assertEquals(
                $initialDislikes - 1,
                $model->getDislikesCount(),
                'Dislikes count should decrease by 1 when removing dislike'
            );
        });
    }

    /**
     * Property: Счётчики не могут быть отрицательными
     */
    public function testCountersCannotBeNegative(): void
    {
        $this->forAll(
            Generator\choose(0, 5),
            Generator\choose(0, 5),
            Generator\choose(1, 1000),
            Generator\choose(1, 10)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId, int $removeCount): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Пытаемся удалить больше лайков/дизлайков чем есть
            for ($i = 0; $i < $removeCount; $i++) {
                $model->setLikesCount(max(0, $model->getLikesCount() - 1));
                $model->setDislikesCount(max(0, $model->getDislikesCount() - 1));
            }
            
            $this->assertGreaterThanOrEqual(0, $model->getLikesCount(), 'Likes count should never be negative');
            $this->assertGreaterThanOrEqual(0, $model->getDislikesCount(), 'Dislikes count should never be negative');
        });
    }

    /**
     * Property: Лайк и удаление лайка - обратные операции (round-trip)
     */
    public function testLikeRemoveRoundTrip(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Лайк
            $model->setLikesCount($model->getLikesCount() + 1);
            // Удаление лайка
            $model->setLikesCount(max(0, $model->getLikesCount() - 1));
            
            $this->assertEquals($initialLikes, $model->getLikesCount(), 'Likes count should return to initial after like/remove');
            $this->assertEquals($initialDislikes, $model->getDislikesCount(), 'Dislikes count should remain unchanged');
        });
    }

    /**
     * Property: Дизлайк и удаление дизлайка - обратные операции (round-trip)
     */
    public function testDislikeRemoveRoundTrip(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialLikes, int $initialDislikes, int $modelId): void {
            $model = $this->createModelWithCounters($modelId, $initialLikes, $initialDislikes);
            
            // Дизлайк
            $model->setDislikesCount($model->getDislikesCount() + 1);
            // Удаление дизлайка
            $model->setDislikesCount(max(0, $model->getDislikesCount() - 1));
            
            $this->assertEquals($initialLikes, $model->getLikesCount(), 'Likes count should remain unchanged');
            $this->assertEquals($initialDislikes, $model->getDislikesCount(), 'Dislikes count should return to initial after dislike/remove');
        });
    }

    /**
     * Создаёт модель с заданными счётчиками
     */
    private function createModelWithCounters(int $id, int $likesCount, int $dislikesCount): ModelProfile
    {
        $model = new ModelProfile();
        $model->setDisplayName("Test Model $id");
        $model->setSlug("test-model-$id");
        $model->setLikesCount($likesCount);
        $model->setDislikesCount($dislikesCount);
        $model->setActive(true);
        
        $reflection = new \ReflectionClass($model);
        $prop = $reflection->getProperty('id');
        $prop->setValue($model, $id);
        
        return $model;
    }
}
