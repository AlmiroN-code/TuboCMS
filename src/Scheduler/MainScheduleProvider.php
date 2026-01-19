<?php

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Главный провайдер расписания для периодических задач.
 * 
 * Задачи:
 * - Очистка временных файлов
 * - Обновление статистики
 * - Генерация sitemap
 * - Очистка старых уведомлений
 */
#[AsSchedule('main')]
class MainScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // Очистка временных файлов каждые 6 часов
            ->add(RecurringMessage::every('6 hours', new Message\CleanupTempFilesMessage()))
            
            // Обновление статистики каждый час
            ->add(RecurringMessage::every('1 hour', new Message\UpdateStatsMessage()))
            
            // Генерация sitemap каждый день в 3:00
            ->add(RecurringMessage::cron('0 3 * * *', new Message\GenerateSitemapMessage()))
            
            // Очистка старых уведомлений каждую неделю
            ->add(RecurringMessage::every('1 week', new Message\CleanupOldNotificationsMessage()))
            
            // Обновление счетчиков категорий каждые 30 минут
            ->add(RecurringMessage::every('30 minutes', new Message\UpdateCategoryCountersMessage()))
            
            // Очистка неактивных сессий каждый день
            ->add(RecurringMessage::cron('0 4 * * *', new Message\CleanupSessionsMessage()))
            
            // Проверка застрявших видео каждые 15 минут
            ->add(RecurringMessage::every('15 minutes', new Message\CheckStuckVideosMessage()));
    }
}
