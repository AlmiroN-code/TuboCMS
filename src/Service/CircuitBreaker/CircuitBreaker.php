<?php

declare(strict_types=1);

namespace App\Service\CircuitBreaker;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Реализация паттерна Circuit Breaker для защиты от каскадных сбоев
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    private const CACHE_PREFIX = 'circuit_breaker_';

    public function __construct(
        private readonly string $name,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 60,
        private readonly int $expectedExceptionThreshold = 10,
    ) {}

    /**
     * Выполнить операцию с защитой Circuit Breaker
     */
    public function call(callable $operation, ?callable $fallback = null): mixed
    {
        $state = $this->getState();

        if ($state === CircuitBreakerState::OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->setState(CircuitBreakerState::HALF_OPEN);
                $this->logger->info('Circuit breaker transitioning to HALF_OPEN', [
                    'name' => $this->name
                ]);
            } else {
                return $this->executeFallback($fallback);
            }
        }

        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($e);
            return $this->executeFallback($fallback, $e);
        }
    }

    /**
     * Получить текущее состояние
     */
    public function getState(): CircuitBreakerState
    {
        $stateData = $this->getStateData();
        return CircuitBreakerState::from($stateData['state'] ?? 'closed');
    }

    /**
     * Сбросить Circuit Breaker в закрытое состояние
     */
    public function reset(): void
    {
        $this->setState(CircuitBreakerState::CLOSED);
        $this->resetCounters();
        $this->logger->info('Circuit breaker reset to CLOSED', [
            'name' => $this->name
        ]);
    }

    /**
     * Принудительно открыть Circuit Breaker
     */
    public function forceOpen(): void
    {
        $this->setState(CircuitBreakerState::OPEN);
        $this->logger->warning('Circuit breaker forced to OPEN', [
            'name' => $this->name
        ]);
    }

    /**
     * Обработка успешного выполнения
     */
    private function onSuccess(): void
    {
        $state = $this->getState();
        
        if ($state === CircuitBreakerState::HALF_OPEN) {
            $this->setState(CircuitBreakerState::CLOSED);
            $this->resetCounters();
            $this->logger->info('Circuit breaker recovered to CLOSED', [
                'name' => $this->name
            ]);
        } elseif ($state === CircuitBreakerState::CLOSED) {
            $this->resetFailureCount();
        }
    }

    /**
     * Обработка неудачного выполнения
     */
    private function onFailure(\Throwable $e): void
    {
        $failureCount = $this->incrementFailureCount();
        $state = $this->getState();

        $this->logger->warning('Circuit breaker recorded failure', [
            'name' => $this->name,
            'failure_count' => $failureCount,
            'state' => $state->value,
            'exception' => $e->getMessage()
        ]);

        if ($state === CircuitBreakerState::HALF_OPEN) {
            $this->setState(CircuitBreakerState::OPEN);
            $this->setLastFailureTime();
            $this->logger->warning('Circuit breaker opened from HALF_OPEN', [
                'name' => $this->name
            ]);
        } elseif ($state === CircuitBreakerState::CLOSED && $failureCount >= $this->failureThreshold) {
            $this->setState(CircuitBreakerState::OPEN);
            $this->setLastFailureTime();
            $this->logger->error('Circuit breaker opened due to failure threshold', [
                'name' => $this->name,
                'failure_count' => $failureCount,
                'threshold' => $this->failureThreshold
            ]);
        }
    }

    /**
     * Выполнить fallback функцию
     */
    private function executeFallback(?callable $fallback, ?\Throwable $originalException = null): mixed
    {
        if ($fallback === null) {
            throw new CircuitBreakerOpenException(
                "Circuit breaker '{$this->name}' is open",
                0,
                $originalException
            );
        }

        try {
            return $fallback($originalException);
        } catch (\Throwable $e) {
            $this->logger->error('Fallback function failed', [
                'name' => $this->name,
                'fallback_error' => $e->getMessage(),
                'original_error' => $originalException?->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Проверить, нужно ли попытаться сбросить Circuit Breaker
     */
    private function shouldAttemptReset(): bool
    {
        $stateData = $this->getStateData();
        $lastFailureTime = $stateData['last_failure_time'] ?? 0;
        
        return (time() - $lastFailureTime) >= $this->recoveryTimeout;
    }

    /**
     * Установить состояние
     */
    private function setState(CircuitBreakerState $state): void
    {
        $stateData = $this->getStateData();
        $stateData['state'] = $state->value;
        $this->saveStateData($stateData);
    }

    /**
     * Увеличить счетчик неудач
     */
    private function incrementFailureCount(): int
    {
        $stateData = $this->getStateData();
        $stateData['failure_count'] = ($stateData['failure_count'] ?? 0) + 1;
        $this->saveStateData($stateData);
        
        return $stateData['failure_count'];
    }

    /**
     * Сбросить счетчик неудач
     */
    private function resetFailureCount(): void
    {
        $stateData = $this->getStateData();
        $stateData['failure_count'] = 0;
        $this->saveStateData($stateData);
    }

    /**
     * Установить время последней неудачи
     */
    private function setLastFailureTime(): void
    {
        $stateData = $this->getStateData();
        $stateData['last_failure_time'] = time();
        $this->saveStateData($stateData);
    }

    /**
     * Сбросить все счетчики
     */
    private function resetCounters(): void
    {
        $stateData = [
            'state' => CircuitBreakerState::CLOSED->value,
            'failure_count' => 0,
            'last_failure_time' => 0
        ];
        $this->saveStateData($stateData);
    }

    /**
     * Получить данные состояния из кеша
     */
    private function getStateData(): array
    {
        $key = self::CACHE_PREFIX . $this->name;
        return $this->cache->get($key, fn() => [
            'state' => CircuitBreakerState::CLOSED->value,
            'failure_count' => 0,
            'last_failure_time' => 0
        ]);
    }

    /**
     * Сохранить данные состояния в кеш
     */
    private function saveStateData(array $data): void
    {
        $key = self::CACHE_PREFIX . $this->name;
        $this->cache->delete($key);
        $this->cache->get($key, fn() => $data);
    }
}