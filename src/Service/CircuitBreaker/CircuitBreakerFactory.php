<?php

declare(strict_types=1);

namespace App\Service\CircuitBreaker;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Фабрика для создания Circuit Breaker'ов
 */
class CircuitBreakerFactory
{
    private array $circuitBreakers = [];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Создать или получить Circuit Breaker
     */
    public function create(
        string $name,
        int $failureThreshold = 5,
        int $recoveryTimeout = 60,
        int $expectedExceptionThreshold = 10
    ): CircuitBreakerInterface {
        if (!isset($this->circuitBreakers[$name])) {
            $this->circuitBreakers[$name] = new CircuitBreaker(
                $name,
                $this->cache,
                $this->logger,
                $failureThreshold,
                $recoveryTimeout,
                $expectedExceptionThreshold
            );
        }

        return $this->circuitBreakers[$name];
    }

    /**
     * Получить все Circuit Breaker'ы
     */
    public function getAll(): array
    {
        return $this->circuitBreakers;
    }

    /**
     * Сбросить все Circuit Breaker'ы
     */
    public function resetAll(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->reset();
        }
    }
}