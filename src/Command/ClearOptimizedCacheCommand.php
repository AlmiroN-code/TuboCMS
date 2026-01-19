<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use App\Service\SettingsService;

#[AsCommand(
    name: 'app:cache:clear-optimized',
    description: 'Очистка оптимизированного кеша приложения'
)]
class ClearOptimizedCacheCommand extends Command
{
    public function __construct(
        private CacheInterface $cache,
        private SettingsService $settingsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('videos', null, InputOption::VALUE_NONE, 'Очистить кеш видео')
            ->addOption('search', null, InputOption::VALUE_NONE, 'Очистить кеш поиска')
            ->addOption('settings', null, InputOption::VALUE_NONE, 'Очистить кеш настроек')
            ->addOption('home', null, InputOption::VALUE_NONE, 'Очистить кеш главной страницы')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Очистить весь кеш');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Очистка оптимизированного кеша');

        $cleared = [];

        if ($input->getOption('all')) {
            $this->clearAllCache($io);
            $cleared[] = 'весь кеш';
        } else {
            if ($input->getOption('videos')) {
                $this->clearVideoCache($io);
                $cleared[] = 'кеш видео';
            }

            if ($input->getOption('search')) {
                $this->clearSearchCache($io);
                $cleared[] = 'кеш поиска';
            }

            if ($input->getOption('settings')) {
                $this->clearSettingsCache($io);
                $cleared[] = 'кеш настроек';
            }

            if ($input->getOption('home')) {
                $this->clearHomeCache($io);
                $cleared[] = 'кеш главной страницы';
            }
        }

        if (empty($cleared)) {
            $io->note('Не выбраны опции для очистки. Используйте --help для просмотра доступных опций.');
            return Command::SUCCESS;
        }

        $io->success('Очищен: ' . implode(', ', $cleared));

        return Command::SUCCESS;
    }

    private function clearAllCache(SymfonyStyle $io): void
    {
        try {
            $this->cache->clear();
            $this->settingsService->clearCache();
            $io->text('✓ Весь кеш очищен');
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке всего кеша: ' . $e->getMessage());
        }
    }

    private function clearVideoCache(SymfonyStyle $io): void
    {
        try {
            // Очищаем кеш связанных видео
            $patterns = [
                'related_videos_*',
                'see_also_*',
                'similar_tags_videos_*',
                'model_videos_*',
                'home_featured_videos_*',
                'home_new_videos_*',
                'home_popular_videos_*',
                'home_recently_watched_*'
            ];

            foreach ($patterns as $pattern) {
                $this->clearCacheByPattern($pattern);
            }

            $io->text('✓ Кеш видео очищен');
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке кеша видео: ' . $e->getMessage());
        }
    }

    private function clearSearchCache(SymfonyStyle $io): void
    {
        try {
            $this->clearCacheByPattern('search_*');
            $io->text('✓ Кеш поиска очищен');
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке кеша поиска: ' . $e->getMessage());
        }
    }

    private function clearSettingsCache(SymfonyStyle $io): void
    {
        try {
            $this->settingsService->clearCache();
            $io->text('✓ Кеш настроек очищен');
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке кеша настроек: ' . $e->getMessage());
        }
    }

    private function clearHomeCache(SymfonyStyle $io): void
    {
        try {
            $patterns = [
                'home_*',
                'home_categories'
            ];

            foreach ($patterns as $pattern) {
                $this->clearCacheByPattern($pattern);
            }

            $io->text('✓ Кеш главной страницы очищен');
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке кеша главной страницы: ' . $e->getMessage());
        }
    }

    private function clearCacheByPattern(string $pattern): void
    {
        // Поскольку Symfony Cache не поддерживает wildcard удаление,
        // мы используем теги если кеш поддерживает их
        if ($this->cache instanceof TagAwareCacheInterface) {
            $tag = str_replace('*', '', $pattern);
            $this->cache->invalidateTags([$tag]);
        } else {
            // Для простого кеша очищаем все
            $this->cache->clear();
        }
    }
}