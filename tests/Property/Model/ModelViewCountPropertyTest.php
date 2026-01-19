<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelProfile;
use App\Entity\User;
use App\Service\ModelStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Property-based tests for model view count functionality.
 * 
 * **Feature: models-section, Property 8: Просмотры корректно подсчитываются с защитой от накрутки**
 * **Validates: Requirements 5.1, 5.2**
 * 
 * Property: Для любой модели и любой сессии пользователя, первый просмотр профиля 
 * увеличивает viewsCount на 1, повторные просмотры в той же сессии не увеличивают счётчик.
 */
class ModelViewCountPropertyTest extends TestCase
{
    use TestTrait;

    private ModelStatsService $service;

    protected function setUp(): void
    {
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        
        $this->service = new ModelStatsService($em);
    }

    /**
     * Property: Первый просмотр увеличивает счётчик на 1
     * 
     * Для любой модели с любым начальным количеством просмотров,
     * первый просмотр в новой сессии должен увеличить viewsCount ровно на 1.
     */
    public function testFirstViewIncrementsCountByOne(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialViews, int $modelId): void {
            // Создаём модель с начальным количеством просмотров
            $model = $this->createModelWithId($modelId, $initialViews);
            
            // Создаём новую сессию
            $session = new Session(new MockArraySessionStorage());
            
            // Выполняем первый просмотр
            $result = $this->service->incrementViewCount($model, null, $session);
            
            // Проверяем что просмотр был засчитан
            $this->assertTrue($result, 'First view should be counted');
            
            // Проверяем что счётчик увеличился ровно на 1
            $this->assertEquals(
                $initialViews + 1,
                $model->getViewsCount(),
                'View count should increase by exactly 1 after first view'
            );
        });
    }

    /**
     * Property: Повторные просмотры в той же сессии не увеличивают счётчик
     * 
     * Для любой модели и любого количества повторных просмотров в одной сессии,
     * счётчик должен увеличиться только один раз (при первом просмотре).
     */
    public function testRepeatedViewsInSameSessionDoNotIncrementCount(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000),
            Generator\choose(2, 10)
        )->withMaxSize(100)->then(function (int $initialViews, int $modelId, int $repeatCount): void {
            // Создаём модель с начальным количеством просмотров
            $model = $this->createModelWithId($modelId, $initialViews);
            
            // Создаём сессию
            $session = new Session(new MockArraySessionStorage());
            
            // Выполняем первый просмотр
            $firstResult = $this->service->incrementViewCount($model, null, $session);
            $this->assertTrue($firstResult, 'First view should be counted');
            
            $viewsAfterFirst = $model->getViewsCount();
            
            // Выполняем повторные просмотры
            for ($i = 0; $i < $repeatCount; $i++) {
                $result = $this->service->incrementViewCount($model, null, $session);
                $this->assertFalse($result, "Repeated view #$i should not be counted");
            }
            
            // Проверяем что счётчик не изменился после первого просмотра
            $this->assertEquals(
                $viewsAfterFirst,
                $model->getViewsCount(),
                'View count should not change after repeated views in same session'
            );
            
            // Проверяем что счётчик увеличился ровно на 1 от начального значения
            $this->assertEquals(
                $initialViews + 1,
                $model->getViewsCount(),
                'View count should be exactly initialViews + 1'
            );
        });
    }

    /**
     * Property: Разные сессии увеличивают счётчик независимо
     * 
     * Для любой модели и любого количества разных сессий,
     * каждая новая сессия должна увеличить счётчик на 1.
     */
    public function testDifferentSessionsIncrementCountIndependently(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000),
            Generator\choose(2, 10)
        )->withMaxSize(100)->then(function (int $initialViews, int $modelId, int $sessionCount): void {
            // Создаём модель с начальным количеством просмотров
            $model = $this->createModelWithId($modelId, $initialViews);
            
            // Выполняем просмотры из разных сессий
            for ($i = 0; $i < $sessionCount; $i++) {
                $session = new Session(new MockArraySessionStorage());
                $result = $this->service->incrementViewCount($model, null, $session);
                $this->assertTrue($result, "View from session #$i should be counted");
            }
            
            // Проверяем что счётчик увеличился на количество сессий
            $this->assertEquals(
                $initialViews + $sessionCount,
                $model->getViewsCount(),
                "View count should be initialViews + $sessionCount"
            );
        });
    }

    /**
     * Property: Просмотры работают одинаково для авторизованных и неавторизованных пользователей
     * 
     * Защита от накрутки основана на сессии, а не на пользователе.
     */
    public function testViewCountWorksForBothAuthenticatedAndAnonymousUsers(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialViews, int $modelId): void {
            // Создаём модель
            $model = $this->createModelWithId($modelId, $initialViews);
            
            // Создаём пользователя
            $user = new User();
            
            // Тест с авторизованным пользователем
            $session1 = new Session(new MockArraySessionStorage());
            $result1 = $this->service->incrementViewCount($model, $user, $session1);
            $this->assertTrue($result1, 'First view with user should be counted');
            
            // Тест с анонимным пользователем (другая сессия)
            $session2 = new Session(new MockArraySessionStorage());
            $result2 = $this->service->incrementViewCount($model, null, $session2);
            $this->assertTrue($result2, 'First view without user should be counted');
            
            // Проверяем что оба просмотра засчитаны
            $this->assertEquals(
                $initialViews + 2,
                $model->getViewsCount(),
                'Both authenticated and anonymous views should be counted'
            );
        });
    }

    /**
     * Создаёт модель с заданным ID и начальным количеством просмотров
     */
    private function createModelWithId(int $id, int $viewsCount): ModelProfile
    {
        $model = new ModelProfile();
        $model->setDisplayName("Test Model $id");
        $model->setSlug("test-model-$id");
        $model->setViewsCount($viewsCount);
        $model->setActive(true);
        
        // Устанавливаем ID через рефлексию
        $reflection = new \ReflectionClass($model);
        $prop = $reflection->getProperty('id');
        $prop->setValue($model, $id);
        
        return $model;
    }
}
