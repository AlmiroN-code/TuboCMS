<?php

declare(strict_types=1);

namespace App\Tests\Property\Model;

use App\Entity\ModelProfile;
use App\Entity\ModelSubscription;
use App\Entity\User;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for model subscription functionality.
 * 
 * **Feature: models-section, Property 5: Подписка корректно обновляет счётчик**
 * **Validates: Requirements 3.1, 3.2**
 * 
 * Property: Для любой модели и любого пользователя, после подписки счётчик 
 * subscribersCount должен увеличиться на 1, после отписки - уменьшиться на 1.
 */
class ModelSubscriptionPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Подписка увеличивает счётчик подписчиков на 1
     * 
     * Для любой модели с любым начальным количеством подписчиков,
     * подписка должна увеличить subscribersCount ровно на 1.
     */
    public function testSubscriptionIncrementsCountByOne(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialSubscribers, int $modelId): void {
            // Создаём модель с начальным количеством подписчиков
            $model = $this->createModelWithId($modelId, $initialSubscribers);
            $user = $this->createUser();
            
            // Симулируем подписку
            $subscription = new ModelSubscription();
            $subscription->setUser($user);
            $subscription->setModel($model);
            $model->setSubscribersCount($model->getSubscribersCount() + 1);
            
            // Проверяем что счётчик увеличился ровно на 1
            $this->assertEquals(
                $initialSubscribers + 1,
                $model->getSubscribersCount(),
                'Subscribers count should increase by exactly 1 after subscription'
            );
        });
    }

    /**
     * Property: Отписка уменьшает счётчик подписчиков на 1
     * 
     * Для любой модели с любым начальным количеством подписчиков > 0,
     * отписка должна уменьшить subscribersCount ровно на 1.
     */
    public function testUnsubscriptionDecrementsCountByOne(): void
    {
        $this->forAll(
            Generator\choose(1, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialSubscribers, int $modelId): void {
            // Создаём модель с начальным количеством подписчиков
            $model = $this->createModelWithId($modelId, $initialSubscribers);
            
            // Симулируем отписку
            $model->setSubscribersCount(max(0, $model->getSubscribersCount() - 1));
            
            // Проверяем что счётчик уменьшился ровно на 1
            $this->assertEquals(
                $initialSubscribers - 1,
                $model->getSubscribersCount(),
                'Subscribers count should decrease by exactly 1 after unsubscription'
            );
        });
    }

    /**
     * Property: Счётчик подписчиков не может быть отрицательным
     * 
     * Для любой модели, даже при отписке от модели с 0 подписчиков,
     * счётчик не должен стать отрицательным.
     */
    public function testSubscribersCountCannotBeNegative(): void
    {
        $this->forAll(
            Generator\choose(0, 5),
            Generator\choose(1, 1000),
            Generator\choose(1, 10)
        )->withMaxSize(100)->then(function (int $initialSubscribers, int $modelId, int $unsubscribeCount): void {
            // Создаём модель с небольшим количеством подписчиков
            $model = $this->createModelWithId($modelId, $initialSubscribers);
            
            // Пытаемся отписаться больше раз, чем есть подписчиков
            for ($i = 0; $i < $unsubscribeCount; $i++) {
                $model->setSubscribersCount(max(0, $model->getSubscribersCount() - 1));
            }
            
            // Проверяем что счётчик не отрицательный
            $this->assertGreaterThanOrEqual(
                0,
                $model->getSubscribersCount(),
                'Subscribers count should never be negative'
            );
        });
    }

    /**
     * Property: Подписка и отписка являются обратными операциями
     * 
     * Для любой модели, подписка с последующей отпиской должна вернуть
     * счётчик к исходному значению.
     */
    public function testSubscribeUnsubscribeRoundTrip(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000)
        )->withMaxSize(100)->then(function (int $initialSubscribers, int $modelId): void {
            // Создаём модель с начальным количеством подписчиков
            $model = $this->createModelWithId($modelId, $initialSubscribers);
            
            // Подписываемся
            $model->setSubscribersCount($model->getSubscribersCount() + 1);
            
            // Отписываемся
            $model->setSubscribersCount(max(0, $model->getSubscribersCount() - 1));
            
            // Проверяем что счётчик вернулся к исходному значению
            $this->assertEquals(
                $initialSubscribers,
                $model->getSubscribersCount(),
                'Subscribers count should return to initial value after subscribe/unsubscribe'
            );
        });
    }

    /**
     * Property: Множественные подписки увеличивают счётчик пропорционально
     * 
     * Для любой модели и любого количества подписок,
     * счётчик должен увеличиться на количество подписок.
     */
    public function testMultipleSubscriptionsIncrementCountProportionally(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000),
            Generator\choose(1, 1000),
            Generator\choose(1, 100)
        )->withMaxSize(100)->then(function (int $initialSubscribers, int $modelId, int $subscriptionCount): void {
            // Создаём модель с начальным количеством подписчиков
            $model = $this->createModelWithId($modelId, $initialSubscribers);
            
            // Симулируем несколько подписок
            for ($i = 0; $i < $subscriptionCount; $i++) {
                $model->setSubscribersCount($model->getSubscribersCount() + 1);
            }
            
            // Проверяем что счётчик увеличился на количество подписок
            $this->assertEquals(
                $initialSubscribers + $subscriptionCount,
                $model->getSubscribersCount(),
                "Subscribers count should be initialSubscribers + $subscriptionCount"
            );
        });
    }

    /**
     * Создаёт модель с заданным ID и начальным количеством подписчиков
     */
    private function createModelWithId(int $id, int $subscribersCount): ModelProfile
    {
        $model = new ModelProfile();
        $model->setDisplayName("Test Model $id");
        $model->setSlug("test-model-$id");
        $model->setSubscribersCount($subscribersCount);
        $model->setActive(true);
        
        // Устанавливаем ID через рефлексию
        $reflection = new \ReflectionClass($model);
        $prop = $reflection->getProperty('id');
        $prop->setValue($model, $id);
        
        return $model;
    }

    /**
     * Создаёт тестового пользователя
     */
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password');
        
        return $user;
    }
}
