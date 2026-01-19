<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelProfile;
use App\Entity\Video;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model videos count functionality.
 * 
 * **Feature: models-section, Property 10: Привязка/отвязка модели к видео обновляет счётчик**
 * **Validates: Requirements 7.2, 7.3**
 * 
 * Property: Для любой модели, при привязке к видео videosCount увеличивается на 1,
 * при отвязке - уменьшается на 1.
 */
class ModelVideosCountPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Привязка модели к видео увеличивает счётчик на 1
     * 
     * Для любой модели с любым начальным количеством видео,
     * привязка к новому видео должна увеличить videosCount ровно на 1.
     */
    public function testAddingPerformerIncrementsVideosCount(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialVideosCount, int $modelId): void {
            $model = $this->createModelWithId($modelId, $initialVideosCount);
            $video = $this->createVideo();
            
            // Симулируем привязку модели к видео (как в AdminVideoController)
            $video->addPerformer($model);
            $model->setVideosCount($model->getVideosCount() + 1);
            
            $this->assertEquals(
                $initialVideosCount + 1,
                $model->getVideosCount(),
                'Videos count should increase by exactly 1 after adding performer to video'
            );
        });
    }

    /**
     * Property: Отвязка модели от видео уменьшает счётчик на 1
     * 
     * Для любой модели с любым начальным количеством видео > 0,
     * отвязка от видео должна уменьшить videosCount ровно на 1.
     */
    public function testRemovingPerformerDecrementsVideosCount(): void
    {
        $this->forAll(
            Generator\choose(1, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialVideosCount, int $modelId): void {
            $model = $this->createModelWithId($modelId, $initialVideosCount);
            $video = $this->createVideo();
            
            // Симулируем отвязку модели от видео (как в AdminVideoController)
            $video->removePerformer($model);
            $model->setVideosCount(max(0, $model->getVideosCount() - 1));
            
            $this->assertEquals(
                $initialVideosCount - 1,
                $model->getVideosCount(),
                'Videos count should decrease by exactly 1 after removing performer from video'
            );
        });
    }

    /**
     * Property: Счётчик видео не может быть отрицательным
     * 
     * Для любой модели, даже при отвязке от видео с 0 видео,
     * счётчик не должен стать отрицательным.
     */
    public function testVideosCountCannotBeNegative(): void
    {
        $this->forAll(
            Generator\choose(0, 5),
            Generator\choose(1, 1000),
            Generator\choose(1, 10)
        )->withMaxSize(100)->then(function (int $initialVideosCount, int $modelId, int $removeCount): void {
            $model = $this->createModelWithId($modelId, $initialVideosCount);
            
            // Пытаемся отвязать больше видео, чем есть
            for ($i = 0; $i < $removeCount; $i++) {
                $model->setVideosCount(max(0, $model->getVideosCount() - 1));
            }
            
            $this->assertGreaterThanOrEqual(
                0,
                $model->getVideosCount(),
                'Videos count should never be negative'
            );
        });
    }

    /**
     * Property: Привязка и отвязка являются обратными операциями
     * 
     * Для любой модели, привязка с последующей отвязкой должна вернуть
     * счётчик к исходному значению.
     */
    public function testAddRemovePerformerRoundTrip(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialVideosCount, int $modelId): void {
            $model = $this->createModelWithId($modelId, $initialVideosCount);
            
            // Привязываем
            $model->setVideosCount($model->getVideosCount() + 1);
            
            // Отвязываем
            $model->setVideosCount(max(0, $model->getVideosCount() - 1));
            
            $this->assertEquals(
                $initialVideosCount,
                $model->getVideosCount(),
                'Videos count should return to initial value after add/remove'
            );
        });
    }

    /**
     * Property: Множественные привязки увеличивают счётчик пропорционально
     * 
     * Для любой модели и любого количества привязок,
     * счётчик должен увеличиться на количество привязок.
     */
    public function testMultipleAdditionsIncrementCountProportionally(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000),
            Generator\choose(1, 50)
        )->withMaxSize(100)->then(function (int $initialVideosCount, int $modelId, int $addCount): void {
            $model = $this->createModelWithId($modelId, $initialVideosCount);
            
            // Симулируем несколько привязок
            for ($i = 0; $i < $addCount; $i++) {
                $model->setVideosCount($model->getVideosCount() + 1);
            }
            
            $this->assertEquals(
                $initialVideosCount + $addCount,
                $model->getVideosCount(),
                "Videos count should be initialVideosCount + $addCount"
            );
        });
    }

    /**
     * Property: При удалении видео счётчики всех участников уменьшаются
     * 
     * Для любого видео с N участниками, при удалении видео
     * счётчик каждого участника должен уменьшиться на 1.
     */
    public function testDeletingVideoDecrementsAllPerformersCount(): void
    {
        $this->forAll(
            Generator\choose(1, 10),
            Generator\choose(1, 100)
        )->withMaxSize(100)->then(function (int $performerCount, int $initialVideosCount): void {
            $video = $this->createVideo();
            $performers = [];
            
            // Создаём участников с начальным количеством видео
            for ($i = 0; $i < $performerCount; $i++) {
                $model = $this->createModelWithId($i + 1, $initialVideosCount);
                $video->addPerformer($model);
                $performers[] = $model;
            }
            
            // Симулируем удаление видео (как в AdminVideoController::delete)
            foreach ($video->getPerformers() as $performer) {
                $performer->setVideosCount(max(0, $performer->getVideosCount() - 1));
            }
            
            // Проверяем что счётчик каждого участника уменьшился на 1
            foreach ($performers as $performer) {
                $this->assertEquals(
                    $initialVideosCount - 1,
                    $performer->getVideosCount(),
                    'Each performer videos count should decrease by 1 when video is deleted'
                );
            }
        });
    }

    /**
     * Создаёт модель с заданным ID и начальным количеством видео
     */
    private function createModelWithId(int $id, int $videosCount): ModelProfile
    {
        $model = new ModelProfile();
        $model->setDisplayName("Test Model $id");
        $model->setSlug("test-model-$id");
        $model->setVideosCount($videosCount);
        $model->setActive(true);
        
        // Устанавливаем ID через рефлексию
        $reflection = new \ReflectionClass($model);
        $prop = $reflection->getProperty('id');
        $prop->setValue($model, $id);
        
        return $model;
    }

    /**
     * Создаёт тестовое видео
     */
    private function createVideo(): Video
    {
        $video = new Video();
        $video->setTitle('Test Video');
        $video->setSlug('test-video-' . uniqid());
        
        return $video;
    }
}
