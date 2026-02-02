<?php

declare(strict_types=1);

namespace App\Service\CircuitBreaker;

enum CircuitBreakerState: string
{
    case CLOSED = 'closed';      // Нормальная работа
    case OPEN = 'open';          // Блокировка вызовов
    case HALF_OPEN = 'half_open'; // Тестирование восстановления
}