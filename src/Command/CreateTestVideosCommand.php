<?php

namespace App\Command;

use App\Entity\Video;
use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\UserRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(
    name: 'app:create-test-videos',
    description: 'Создать тестовые видео для админ-панели',
)]
class CreateTestVideosCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Количество видео для создания', 15)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Удалить существующие тестовые видео');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $force = $input->getOption('force');

        $io->title('Создание тестовых видео');

        // Удаляем существующие тестовые видео если нужно
        if ($force) {
            $io->writeln('🗑️  Удаляю существующие тестовые видео...');
            $this->entityManager->createQuery(
                'DELETE FROM App\Entity\Video v WHERE v.title LIKE :pattern'
            )->setParameter('pattern', 'Тестовое видео %')->execute();
        }

        // Получаем админа
        $admin = $this->userRepository->findOneBy(['email' => 'admin@sexvids.online']);
        if (!$admin) {
            $io->error('Админ не найден. Сначала создайте админа командой: php bin/console app:create-default-admin');
            return Command::FAILURE;
        }

        // Получаем категории и теги
        $categories = $this->categoryRepository->findAll();
        $tags = $this->tagRepository->findAll();

        if (empty($categories)) {
            $io->warning('Категории не найдены. Создаю базовые категории...');
            $this->createBasicCategories();
            $categories = $this->categoryRepository->findAll();
        }

        if (empty($tags)) {
            $io->warning('Теги не найдены. Создаю базовые теги...');
            $this->createBasicTags();
            $tags = $this->tagRepository->findAll();
        }

        $slugger = new AsciiSlugger();
        $statuses = [Video::STATUS_PUBLISHED, Video::STATUS_DRAFT, Video::STATUS_PROCESSING];
        
        $videoTitles = [
            'Красивая блондинка в красном белье',
            'Страстная брюнетка соблазняет камеру',
            'Горячая модель в ванной комнате',
            'Сексуальная девушка в спальне',
            'Эротический танец в студии',
            'Модель в кружевном белье',
            'Соблазнительная поза на кровати',
            'Красотка в черных чулках',
            'Игривая девушка с игрушками',
            'Страстная модель у окна',
            'Сексуальная фотосессия дома',
            'Эротическая съемка в душе',
            'Горячая блондинка раздевается',
            'Красивая модель позирует',
            'Соблазнительная брюнетка дразнит'
        ];

        $descriptions = [
            'Невероятно красивая модель демонстрирует свою фигуру в эротической фотосессии.',
            'Страстная и соблазнительная девушка покажет вам все свои прелести.',
            'Горячая модель в интимной обстановке раскрывает свою сексуальность.',
            'Эротическая съемка с участием очаровательной красотки.',
            'Сексуальная модель в откровенных позах для ваших фантазий.',
            'Красивая девушка соблазняет взглядом и грациозными движениями.',
            'Интимная фотосессия с участием потрясающей модели.',
            'Эротическое видео с красивой и страстной девушкой.',
            'Соблазнительная модель показывает свою естественную красоту.',
            'Горячая съемка в приватной обстановке с очаровательной моделью.'
        ];

        $io->progressStart($count);

        for ($i = 1; $i <= $count; $i++) {
            $video = new Video();
            
            // Используем заготовленные названия или генерируем
            $title = $videoTitles[$i - 1] ?? "Тестовое видео #{$i}";
            $video->setTitle($title);
            
            $description = $descriptions[array_rand($descriptions)];
            $video->setDescription($description);
            
            // Генерируем уникальный slug
            $baseSlug = $slugger->slug($title)->lower();
            $slug = $baseSlug;
            $counter = 1;
            
            while ($this->entityManager->getRepository(Video::class)->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $video->setSlug($slug);
            
            // Случайный статус
            $video->setStatus($statuses[array_rand($statuses)]);
            
            // Случайные параметры
            $video->setFeatured(rand(0, 100) < 20); // 20% шанс быть рекомендуемым
            $video->setDuration(rand(300, 3600)); // От 5 минут до 1 часа
            $video->setViewsCount(rand(0, 10000));
            $video->setLikesCount(rand(0, 500));
            $video->setCommentsCount(rand(0, 50));
            
            // Устанавливаем автора
            $video->setCreatedBy($admin);
            
            // Добавляем случайные категории (1-3)
            if (!empty($categories)) {
                $categoryCount = rand(1, min(3, count($categories)));
                if ($categoryCount === 1) {
                    $selectedCategories = [array_rand($categories)];
                } else {
                    $selectedCategories = array_rand($categories, $categoryCount);
                    if (!is_array($selectedCategories)) {
                        $selectedCategories = [$selectedCategories];
                    }
                }
                
                foreach ($selectedCategories as $categoryIndex) {
                    $video->addCategory($categories[$categoryIndex]);
                }
            }
            
            // Добавляем случайные теги (2-5)
            if (!empty($tags)) {
                $maxTags = min(5, count($tags));
                $tagCount = rand(1, $maxTags);
                
                if ($tagCount === 1) {
                    $selectedTags = [array_rand($tags)];
                } else {
                    $selectedTags = array_rand($tags, $tagCount);
                    if (!is_array($selectedTags)) {
                        $selectedTags = [$selectedTags];
                    }
                }
                
                foreach ($selectedTags as $tagIndex) {
                    $video->addTag($tags[$tagIndex]);
                }
            }
            
            // Устанавливаем даты
            $createdAt = new \DateTimeImmutable('-' . rand(1, 30) . ' days');
            $video->setCreatedAt($createdAt);
            $video->setUpdatedAt($createdAt);
            
            $this->entityManager->persist($video);
            
            if ($i % 5 === 0) {
                $this->entityManager->flush();
            }
            
            $io->progressAdvance();
        }
        
        $this->entityManager->flush();
        $io->progressFinish();

        $io->success([
            "✅ Создано {$count} тестовых видео!",
            '',
            '📋 Статистика:',
            "   - Опубликованных: ~" . round($count * 0.4),
            "   - Черновиков: ~" . round($count * 0.4), 
            "   - В обработке: ~" . round($count * 0.2),
            '',
            '🔗 Перейти в админ-панель: /admin/videos'
        ]);

        return Command::SUCCESS;
    }

    private function createBasicCategories(): void
    {
        $categories = [
            ['name' => 'Блондинки', 'slug' => 'blondes'],
            ['name' => 'Брюнетки', 'slug' => 'brunettes'],
            ['name' => 'Рыжие', 'slug' => 'redheads'],
            ['name' => 'Большая грудь', 'slug' => 'big-boobs'],
            ['name' => 'Стройные', 'slug' => 'skinny'],
        ];

        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setSlug($categoryData['slug']);
            $category->setActive(true);
            $this->entityManager->persist($category);
        }

        $this->entityManager->flush();
    }

    private function createBasicTags(): void
    {
        $tags = [
            ['name' => 'Красивая', 'slug' => 'beautiful'],
            ['name' => 'Сексуальная', 'slug' => 'sexy'],
            ['name' => 'Горячая', 'slug' => 'hot'],
            ['name' => 'Эротика', 'slug' => 'erotic'],
            ['name' => 'Соло', 'slug' => 'solo'],
            ['name' => 'Белье', 'slug' => 'lingerie'],
            ['name' => 'Стриптиз', 'slug' => 'striptease'],
            ['name' => 'Позирование', 'slug' => 'posing'],
        ];

        foreach ($tags as $tagData) {
            $tag = new Tag();
            $tag->setName($tagData['name']);
            $tag->setSlug($tagData['slug']);
            $this->entityManager->persist($tag);
        }

        $this->entityManager->flush();
    }
}