<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/scheduler')]
#[IsGranted('ROLE_ADMIN')]
class AdminSchedulerController extends AbstractController
{
    #[Route('', name: 'admin_scheduler', methods: ['GET'])]
    public function index(): Response
    {
        $tasks = [
            [
                'name' => 'CleanupTempFiles',
                'description' => 'Очистка временных файлов',
                'schedule' => 'Каждые 6 часов',
                'icon' => '🗑️',
            ],
            [
                'name' => 'UpdateStats',
                'description' => 'Обновление статистики',
                'schedule' => 'Каждый час',
                'icon' => '📊',
            ],
            [
                'name' => 'GenerateSitemap',
                'description' => 'Генерация sitemap.xml',
                'schedule' => 'Ежедневно в 3:00',
                'icon' => '🗺️',
            ],
            [
                'name' => 'CleanupOldNotifications',
                'description' => 'Очистка старых уведомлений',
                'schedule' => 'Еженедельно',
                'icon' => '🔔',
            ],
            [
                'name' => 'UpdateCategoryCounters',
                'description' => 'Обновление счетчиков категорий',
                'schedule' => 'Каждые 30 минут',
                'icon' => '📁',
            ],
            [
                'name' => 'CleanupSessions',
                'description' => 'Очистка неактивных сессий',
                'schedule' => 'Ежедневно в 4:00',
                'icon' => '🔐',
            ],
            [
                'name' => 'CheckStuckVideos',
                'description' => 'Проверка застрявших видео',
                'schedule' => 'Каждые 15 минут',
                'icon' => '🎬',
            ],
        ];

        return $this->render('admin/scheduler/index.html.twig', [
            'tasks' => $tasks,
        ]);
    }
}
