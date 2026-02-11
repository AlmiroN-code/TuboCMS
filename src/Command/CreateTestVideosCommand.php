<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-test-videos',
    description: 'Создаёт 45 тестовых опубликованных видео'
)]
class CreateTestVideosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Получаем или создаём тестового пользователя
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setUsername('testuser');
            $user->setPassword('$2y$13$test'); // Dummy hash
            $user->setRoles(['ROLE_USER']);
            
            $this->em->persist($user);
            $this->em->flush();
            
            $io->success('Создан тестовый пользователь: test@example.com');
        }

        $titles = [
            // Первые 15
            'Введение в PHP 8.4',
            'Symfony 8.0 - Новые возможности',
            'Doctrine ORM - Лучшие практики',
            'Tailwind CSS для начинающих',
            'Stimulus JS - Интерактивность',
            'MySQL оптимизация запросов',
            'Docker для разработчиков',
            'Git и GitHub - Полное руководство',
            'REST API с Symfony',
            'Тестирование с PHPUnit',
            'Webpack Encore настройка',
            'Безопасность веб-приложений',
            'Redis кэширование',
            'Nginx конфигурация',
            'Микросервисы на PHP',
            // Дополнительные 30
            'JavaScript ES2024 новинки',
            'TypeScript для PHP разработчиков',
            'React основы и хуки',
            'Vue.js 3 Composition API',
            'Node.js и Express.js',
            'GraphQL с Apollo Server',
            'MongoDB для начинающих',
            'PostgreSQL продвинутые запросы',
            'Elasticsearch полнотекстовый поиск',
            'RabbitMQ очереди сообщений',
            'Kubernetes оркестрация контейнеров',
            'CI/CD с GitHub Actions',
            'AWS облачные сервисы',
            'Serverless архитектура',
            'WebSocket реального времени',
            'OAuth 2.0 и JWT авторизация',
            'SOLID принципы ООП',
            'Design Patterns в PHP',
            'Clean Code практики',
            'Refactoring legacy кода',
            'Performance оптимизация',
            'Профилирование приложений',
            'Мониторинг с Prometheus',
            'Логирование с ELK Stack',
            'Agile и Scrum методологии',
            'Code Review лучшие практики',
            'Документирование API',
            'OpenAPI спецификация',
            'Postman для тестирования API',
            'Linux командная строка',
            'Bash скриптинг',
            'Vim текстовый редактор',
            'SSH и удалённое управление',
            'Cron задачи по расписанию',
            'Systemd управление сервисами'
        ];

        $descriptions = [
            // Первые 15
            'Подробное руководство по новым возможностям PHP 8.4',
            'Обзор всех новых фич Symfony 8.0',
            'Как правильно работать с Doctrine ORM',
            'Изучаем Tailwind CSS с нуля',
            'Создаём интерактивные компоненты со Stimulus',
            'Оптимизируем производительность MySQL',
            'Контейнеризация приложений с Docker',
            'Полный курс по системе контроля версий',
            'Создание RESTful API на Symfony',
            'Пишем качественные тесты для PHP',
            'Настройка сборки фронтенда',
            'Защита от основных уязвимостей',
            'Ускоряем приложение с помощью Redis',
            'Настройка веб-сервера Nginx',
            'Архитектура микросервисов',
            // Дополнительные 30
            'Новые возможности JavaScript 2024',
            'Типизация для PHP разработчиков',
            'Современная разработка на React',
            'Реактивность во Vue.js 3',
            'Backend на JavaScript',
            'Современный подход к API',
            'NoSQL база данных MongoDB',
            'Сложные запросы в PostgreSQL',
            'Полнотекстовый поиск с Elasticsearch',
            'Асинхронная обработка с RabbitMQ',
            'Управление контейнерами в продакшене',
            'Автоматизация развёртывания',
            'Облачная инфраструктура Amazon',
            'Функции без серверов',
            'Двусторонняя связь в реальном времени',
            'Современная аутентификация и авторизация',
            'Принципы объектно-ориентированного программирования',
            'Паттерны проектирования на практике',
            'Написание чистого и понятного кода',
            'Улучшение существующего кода',
            'Ускорение работы приложений',
            'Поиск узких мест в коде',
            'Мониторинг метрик приложения',
            'Централизованное логирование',
            'Гибкая разработка программного обеспечения',
            'Эффективное ревью кода в команде',
            'Создание понятной документации',
            'Стандарт описания REST API',
            'Тестирование HTTP запросов',
            'Основы работы в терминале Linux',
            'Автоматизация задач с Bash',
            'Эффективная работа в консольном редакторе',
            'Безопасное подключение к серверам',
            'Автоматизация повторяющихся задач',
            'Управление системными службами'
        ];

        $io->progressStart(45);

        for ($i = 0; $i < 45; $i++) {
            $video = new Video();
            $video->setTitle($titles[$i]);
            $video->setSlug($this->generateSlug($titles[$i]));
            $video->setDescription($descriptions[$i]);
            $video->setStatus(Video::STATUS_PUBLISHED);
            $video->setCreatedBy($user);
            $video->setDuration(rand(300, 3600)); // 5-60 минут
            
            // Устанавливаем дату создания (разброс от 1 до 7 дней назад)
            $daysAgo = rand(1, 7);
            $createdAt = new \DateTimeImmutable("-{$daysAgo} days");
            $reflection = new \ReflectionClass($video);
            $property = $reflection->getProperty('createdAt');
            $property->setAccessible(true);
            $property->setValue($video, $createdAt);
            
            // Устанавливаем метрики для trending алгоритма
            $video->setViewsCount(rand(100, 5000));
            $video->setLikesCount(rand(10, 500));
            $video->setDislikesCount(rand(0, 50));
            $video->setCommentsCount(rand(5, 100));
            
            $this->em->persist($video);
            $io->progressAdvance();
        }

        $this->em->flush();
        $io->progressFinish();

        $io->success('Успешно создано 45 тестовых видео!');
        $io->info('Все видео опубликованы и доступны на сайте.');

        return Command::SUCCESS;
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Добавляем уникальный ID
        return $slug . '-' . uniqid();
    }
}
