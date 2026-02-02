<?php

declare(strict_types=1);

namespace App\Service\CircuitBreaker;

interface CircuitBreakerInterface
{
    /**
     * Выполнить операцию с защитой Circuit Breaker
     */
    public function call(callable $operation, ?callable $fallback = null): mixed;

    /**
     * Получить текущее состояние
     */
    public function getState(): CircuitBreakerState;

    /**
     * Сбросить Circuit Breaker в закрытое состояние
     */
    public function reset(): void;

    /**
     * Принудительно открыть Circuit Breaker
     */
    public function forceOpen(): void;
}