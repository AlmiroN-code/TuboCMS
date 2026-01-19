<?php

namespace App\Controller\Admin;

use App\Service\MessengerWorkerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/worker')]
#[IsGranted('ROLE_ADMIN')]
class AdminWorkerController extends AbstractController
{
    public function __construct(
        private MessengerWorkerService $workerService
    ) {
    }

    #[Route('', name: 'admin_worker_index')]
    public function index(): Response
    {
        return $this->render('admin/worker/index.html.twig', [
            'status' => $this->workerService->getStatus()
        ]);
    }

    #[Route('/status', name: 'admin_worker_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json($this->workerService->getStatus());
    }

    #[Route('/start', name: 'admin_worker_start', methods: ['POST'])]
    public function start(Request $request): Response
    {
        $result = $this->workerService->start();
        
        if ($request->isXmlHttpRequest()) {
            return $this->json($result);
        }

        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);
        
        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('admin_worker_index'));
    }

    #[Route('/stop', name: 'admin_worker_stop', methods: ['POST'])]
    public function stop(Request $request): Response
    {
        $result = $this->workerService->stop();
        
        if ($request->isXmlHttpRequest()) {
            return $this->json($result);
        }

        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);
        
        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('admin_worker_index'));
    }

    #[Route('/restart', name: 'admin_worker_restart', methods: ['POST'])]
    public function restart(Request $request): Response
    {
        $result = $this->workerService->restart();
        
        if ($request->isXmlHttpRequest()) {
            return $this->json($result);
        }

        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);
        
        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('admin_worker_index'));
    }

    #[Route('/logs', name: 'admin_worker_logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $lines = $request->query->getInt('lines', 100);
        $logs = $this->workerService->getLastLogLines($lines);
        
        if ($request->isXmlHttpRequest()) {
            return $this->json(['logs' => $logs]);
        }

        return $this->render('admin/worker/logs.html.twig', [
            'logs' => $logs,
            'status' => $this->workerService->getStatus()
        ]);
    }

    #[Route('/clear-logs', name: 'admin_worker_clear_logs', methods: ['POST'])]
    public function clearLogs(Request $request): Response
    {
        $success = $this->workerService->clearLog();
        
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => $success]);
        }

        $this->addFlash($success ? 'success' : 'error', $success ? 'Логи очищены' : 'Ошибка очистки логов');
        
        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('admin_worker_logs'));
    }
}
