<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\VideoProcessingService;
use App\Service\SettingsService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

// Инициализируем контейнер
$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../config/services.yaml'));
$loader->load('services.yaml');

// Получаем сервисы
$settingsService = $container->get(SettingsService::class);
$videoProcessor = $container->get(VideoProcessingService::class);

// Тестируем на существующем видео
$testVideoPath = __DIR__ . '/../public/media/videos/720p/1_720p.mp4';
$posterPath = __DIR__ . '/../public/media/posters/test_poster.jpg';

if (!file_exists($testVideoPath)) {
    echo "Видео не найдено: $testVideoPath\n";
    exit(1);
}

echo "Тестирование извлечения постера...\n";
echo "Видео: $testVideoPath\n";
echo "Постер: $posterPath\n";

$result = $videoProcessor->extractPoster($testVideoPath, $posterPath);

echo "Результат: " . ($result ? "успех" : "ошибка") . "\n";

if (file_exists($posterPath)) {
    echo "Размер постера: " . filesize($posterPath) . " байт\n";
} else {
    echo "Файл постера не создан\n";
}
