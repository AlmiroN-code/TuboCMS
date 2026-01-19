<?php

namespace App\Twig;

use App\Service\MessengerWorkerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WorkerExtension extends AbstractExtension
{
    public function __construct(
        private MessengerWorkerService $workerService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('worker_status', [$this, 'getWorkerStatus']),
        ];
    }

    public function getWorkerStatus(): array
    {
        return [
            'running' => $this->workerService->isRunning(),
            'pid' => $this->workerService->getPid(),
        ];
    }
}
