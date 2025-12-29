<?php

namespace App\Service;

use App\Entity\ModelProfile;
use App\Entity\User;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Сервис для работы со статистикой моделей
 */
class ModelStatsService
{
    private const SESSION_VIEWED_MODELS_KEY = 'viewed_models';

    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Увеличивает счётчик просмотров модели с защитой от накрутки через сессию
     * 
     * @param ModelProfile $model Модель
     * @param User|null $user Пользователь (может быть null для гостей)
     * @param SessionInterface $session Сессия пользователя
     * @return bool true если просмотр был засчитан, false если это повторный просмотр
     */
    public function incrementViewCount(ModelProfile $model, ?User $user, SessionInterface $session): bool
    {
        $modelId = $model->getId();
        
        // Получаем список просмотренных моделей из сессии
        $viewedModels = $session->get(self::SESSION_VIEWED_MODELS_KEY, []);
        
        // Проверяем, была ли модель уже просмотрена в этой сессии
        if (\in_array($modelId, $viewedModels, true)) {
            return false;
        }
        
        // Добавляем модель в список просмотренных
        $viewedModels[] = $modelId;
        $session->set(self::SESSION_VIEWED_MODELS_KEY, $viewedModels);
        
        // Увеличиваем счётчик просмотров
        $model->setViewsCount($model->getViewsCount() + 1);
        $this->em->flush();
        
        return true;
    }

    /**
     * Вычисляет знак зодиака по дате рождения
     * 
     * @param \DateTimeInterface $birthDate Дата рождения
     * @return string|null Название знака зодиака или null если дата некорректна
     */
    public function getZodiacSign(\DateTimeInterface $birthDate): ?string
    {
        $day = (int) $birthDate->format('j');
        $month = (int) $birthDate->format('n');

        $zodiacSigns = [
            ['name' => 'capricorn', 'start' => [1, 1], 'end' => [1, 19]],
            ['name' => 'aquarius', 'start' => [1, 20], 'end' => [2, 18]],
            ['name' => 'pisces', 'start' => [2, 19], 'end' => [3, 20]],
            ['name' => 'aries', 'start' => [3, 21], 'end' => [4, 19]],
            ['name' => 'taurus', 'start' => [4, 20], 'end' => [5, 20]],
            ['name' => 'gemini', 'start' => [5, 21], 'end' => [6, 20]],
            ['name' => 'cancer', 'start' => [6, 21], 'end' => [7, 22]],
            ['name' => 'leo', 'start' => [7, 23], 'end' => [8, 22]],
            ['name' => 'virgo', 'start' => [8, 23], 'end' => [9, 22]],
            ['name' => 'libra', 'start' => [9, 23], 'end' => [10, 22]],
            ['name' => 'scorpio', 'start' => [10, 23], 'end' => [11, 21]],
            ['name' => 'sagittarius', 'start' => [11, 22], 'end' => [12, 21]],
            ['name' => 'capricorn', 'start' => [12, 22], 'end' => [12, 31]],
        ];

        foreach ($zodiacSigns as $sign) {
            [$startMonth, $startDay] = $sign['start'];
            [$endMonth, $endDay] = $sign['end'];

            if ($month === $startMonth && $day >= $startDay) {
                return $sign['name'];
            }
            if ($month === $endMonth && $day <= $endDay) {
                return $sign['name'];
            }
        }

        return null;
    }

    /**
     * Вычисляет возраст по дате рождения
     * 
     * @param \DateTimeInterface $birthDate Дата рождения
     * @return int Возраст в годах
     */
    public function calculateAge(\DateTimeInterface $birthDate): int
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($birthDate);
        
        return $diff->y;
    }

    /**
     * Пересчитывает количество видео модели
     * 
     * @param ModelProfile $model Модель
     */
    public function updateVideosCount(ModelProfile $model): void
    {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Video::class, 'v')
            ->innerJoin('v.performers', 'm')
            ->where('m.id = :modelId')
            ->andWhere('v.status = :status')
            ->setParameter('modelId', $model->getId())
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();

        $model->setVideosCount((int) $count);
        $this->em->flush();
    }
}
