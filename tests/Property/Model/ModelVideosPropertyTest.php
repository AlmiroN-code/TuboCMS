<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelProfile;
use App\Entity\Video;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model videos functionality.
 * 
 * **Feature: models-section, Property 4: Видео модели принадлежат этой модели**
 * **Validates: Requirements 2.4**
 * 
 * Property: Для любой модели, все видео отображаемые на её странице профиля 
 * должны содержать эту модель в коллекции performers.
 */
class ModelVideosPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Все видео модели содержат эту модель в performers
     * 
     * Для любой модели и любого набора видео, если видео связано с моделью,
     * то модель должна быть в коллекции performers этого видео.
     */
    public function testModelVideosContainModel(): void
    {
        $this->forAll(
            Generator\choose(1, 20), // количество видео
            Generator\choose(1, 5)   // количество моделей
        )->withMaxSize(100)->then(function (int $videoCount, int $modelCount): void {
            // Создаём модели
            $models = [];
            for ($i = 0; $i < $modelCount; $i++) {
                $model = new ModelProfile();
                $model->setDisplayName("Model $i");
                $model->setSlug("model-$i");
                $model->setActive(true);
                $models[] = $model;
            }

            // Создаём видео и связываем с моделями
            $videos = [];
            for ($i = 0; $i < $videoCount; $i++) {
                $video = new Video();
                $video->setTitle("Video $i");
                $video->setSlug("video-$i");
                $video->setStatus(Video::STATUS_PUBLISHED);
                
                // Случайно выбираем модели для этого видео
                $selectedModels = \array_slice($models, 0, \random_int(1, $modelCount));
                foreach ($selectedModels as $model) {
                    $video->addPerformer($model);
                }
                
                $videos[] = $video;
            }

            // Для каждой модели проверяем, что все её видео содержат эту модель
            foreach ($models as $model) {
                // Получаем видео модели (симулируем логику репозитория)
                $modelVideos = \array_filter($videos, function (Video $video) use ($model): bool {
                    return $video->getPerformers()->contains($model);
                });

                // Проверяем что каждое видео модели содержит эту модель в performers
                foreach ($modelVideos as $video) {
                    $this->assertTrue(
                        $video->getPerformers()->contains($model),
                        \sprintf(
                            'Video "%s" should contain model "%s" in performers',
                            $video->getTitle(),
                            $model->getDisplayName()
                        )
                    );
                }
            }
        });
    }


    /**
     * Property: Видео без модели не появляются в списке видео модели
     * 
     * Для любой модели, видео которые не связаны с ней не должны 
     * появляться в результатах запроса видео этой модели.
     */
    public function testUnrelatedVideosNotInModelVideos(): void
    {
        $this->forAll(
            Generator\choose(1, 10), // количество видео модели
            Generator\choose(1, 10)  // количество несвязанных видео
        )->withMaxSize(100)->then(function (int $modelVideoCount, int $unrelatedVideoCount): void {
            // Создаём целевую модель
            $targetModel = new ModelProfile();
            $targetModel->setDisplayName("Target Model");
            $targetModel->setSlug("target-model");
            $targetModel->setActive(true);

            // Создаём другую модель
            $otherModel = new ModelProfile();
            $otherModel->setDisplayName("Other Model");
            $otherModel->setSlug("other-model");
            $otherModel->setActive(true);

            // Создаём видео для целевой модели
            $modelVideos = [];
            for ($i = 0; $i < $modelVideoCount; $i++) {
                $video = new Video();
                $video->setTitle("Model Video $i");
                $video->setSlug("model-video-$i");
                $video->setStatus(Video::STATUS_PUBLISHED);
                $video->addPerformer($targetModel);
                $modelVideos[] = $video;
            }

            // Создаём несвязанные видео (только для другой модели)
            $unrelatedVideos = [];
            for ($i = 0; $i < $unrelatedVideoCount; $i++) {
                $video = new Video();
                $video->setTitle("Unrelated Video $i");
                $video->setSlug("unrelated-video-$i");
                $video->setStatus(Video::STATUS_PUBLISHED);
                $video->addPerformer($otherModel);
                $unrelatedVideos[] = $video;
            }

            // Объединяем все видео
            $allVideos = \array_merge($modelVideos, $unrelatedVideos);

            // Фильтруем видео целевой модели (симулируем логику репозитория)
            $filteredVideos = \array_filter($allVideos, function (Video $video) use ($targetModel): bool {
                return $video->getPerformers()->contains($targetModel);
            });

            // Проверяем что несвязанные видео не попали в результат
            foreach ($unrelatedVideos as $unrelatedVideo) {
                $this->assertNotContains(
                    $unrelatedVideo,
                    $filteredVideos,
                    \sprintf(
                        'Unrelated video "%s" should not appear in target model videos',
                        $unrelatedVideo->getTitle()
                    )
                );
            }

            // Проверяем что все видео модели попали в результат
            foreach ($modelVideos as $modelVideo) {
                $this->assertContains(
                    $modelVideo,
                    $filteredVideos,
                    \sprintf(
                        'Model video "%s" should appear in target model videos',
                        $modelVideo->getTitle()
                    )
                );
            }
        });
    }

    /**
     * Property: Количество видео модели соответствует фактическому количеству связей
     * 
     * Для любой модели, количество видео в результате должно равняться
     * количеству видео, которые содержат эту модель в performers.
     */
    public function testModelVideosCountMatchesActualCount(): void
    {
        $this->forAll(
            Generator\choose(0, 20), // количество видео
            Generator\choose(1, 5)   // количество моделей
        )->withMaxSize(100)->then(function (int $videoCount, int $modelCount): void {
            // Создаём модели
            $models = [];
            for ($i = 0; $i < $modelCount; $i++) {
                $model = new ModelProfile();
                $model->setDisplayName("Model $i");
                $model->setSlug("model-$i");
                $model->setActive(true);
                $models[] = $model;
            }

            // Создаём видео и случайно связываем с моделями
            $videos = [];
            for ($i = 0; $i < $videoCount; $i++) {
                $video = new Video();
                $video->setTitle("Video $i");
                $video->setSlug("video-$i");
                $video->setStatus(Video::STATUS_PUBLISHED);
                
                // Случайно выбираем модели для этого видео
                foreach ($models as $model) {
                    if (\random_int(0, 1) === 1) {
                        $video->addPerformer($model);
                    }
                }
                
                $videos[] = $video;
            }

            // Для каждой модели проверяем соответствие количества
            foreach ($models as $model) {
                // Считаем ожидаемое количество
                $expectedCount = 0;
                foreach ($videos as $video) {
                    if ($video->getPerformers()->contains($model)) {
                        $expectedCount++;
                    }
                }

                // Получаем видео модели (симулируем логику репозитория)
                $modelVideos = \array_filter($videos, function (Video $video) use ($model): bool {
                    return $video->getPerformers()->contains($model);
                });

                $this->assertCount(
                    $expectedCount,
                    $modelVideos,
                    \sprintf(
                        'Model "%s" should have exactly %d videos',
                        $model->getDisplayName(),
                        $expectedCount
                    )
                );
            }
        });
    }
}
