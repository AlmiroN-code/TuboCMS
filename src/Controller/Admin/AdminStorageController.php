<?php

namespace App\Controller\Admin;

use App\Entity\Storage;
use App\Message\MigrateFileMessage;
use App\Repository\StorageRepository;
use App\Repository\VideoFileRepository;
use App\Service\MigrationReportService;
use App\Service\StorageManager;
use App\Service\StorageStatsService;
use App\Validator\StorageConfigValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контроллер управления хранилищами в админ-панели.
 * 
 * Requirements 1.1: Отображение списка хранилищ со статусом
 * Requirements 1.5: Валидация параметров подключения перед сохранением
 * Requirements 1.6: Тестирование подключения с детальным отчётом
 * Requirements 4.2: Постановка задач миграции в очередь
 * Requirements 6.3: Отображение предупреждения при превышении 80% использования
 */
#[Route('/admin/storage')]
#[IsGranted('ROLE_ADMIN')]
class AdminStorageController extends AbstractController
{
    public function __construct(
        private StorageRepository $storageRepository,
        private VideoFileRepository $videoFileRepository,
        private EntityManagerInterface $em,
        private StorageManager $storageManager,
        private StorageConfigValidator $configValidator,
        private MessageBusInterface $messageBus,
        private MigrationReportService $migrationReportService,
        private StorageStatsService $storageStatsService,
    ) {}

    /**
     * Список всех хранилищ.
     * Requirements 1.1: WHEN an administrator accesses the storage settings page 
     * THEN the System SHALL display a list of configured storages with their status
     * Requirements 6.3: WHEN storage usage exceeds 80% THEN the System SHALL 
     * display a warning notification
     */
    #[Route('', name: 'admin_storage')]
    public function index(): Response
    {
        $storages = $this->storageRepository->findBy([], ['isDefault' => 'DESC', 'name' => 'ASC']);
        
        // Получаем информацию о квотах и предупреждениях для каждого хранилища
        $storageData = $this->getStoragesWithQuotaInfo($storages);
        
        // Собираем хранилища с превышением порога для общего предупреждения
        $storagesWithWarning = [];
        foreach ($storageData as $data) {
            if ($data['warningExceeded']) {
                $storagesWithWarning[] = $data;
            }
        }
        
        return $this->render('admin/storage/index.html.twig', [
            'storages' => $storages,
            'storageData' => $storageData,
            'storagesWithWarning' => $storagesWithWarning,
        ]);
    }

    /**
     * Получить информацию о квотах для списка хранилищ.
     * 
     * Requirements 6.2: WHEN a storage supports quota information THEN the System 
     * SHALL display used and available space
     * Requirements 6.3: WHEN storage usage exceeds 80% THEN the System SHALL 
     * display a warning notification
     * 
     * @param Storage[] $storages
     * @return array<int, array{storage: Storage, quota: \App\Storage\DTO\StorageQuota|null, usagePercent: float|null, warningExceeded: bool, quotaFormatted: array|null}>
     */
    private function getStoragesWithQuotaInfo(array $storages): array
    {
        $result = [];

        foreach ($storages as $storage) {
            $quota = null;
            $usagePercent = null;
            $warningExceeded = false;
            $quotaFormatted = null;

            try {
                $adapter = $this->storageManager->getAdapter($storage);
                $quota = $adapter->getQuota();
                
                if ($quota !== null) {
                    $usagePercent = $quota->getUsagePercent();
                    $warningExceeded = $quota->isWarningThresholdExceeded();
                    $quotaFormatted = [
                        'used' => $this->storageStatsService->formatSize($quota->usedBytes),
                        'total' => $quota->totalBytes !== null 
                            ? $this->storageStatsService->formatSize($quota->totalBytes) 
                            : null,
                        'available' => $quota->getAvailableBytes() !== null 
                            ? $this->storageStatsService->formatSize($quota->getAvailableBytes()) 
                            : null,
                    ];
                }
            } catch (\Throwable) {
                // Игнорируем ошибки получения квоты
            }

            $result[$storage->getId()] = [
                'storage' => $storage,
                'quota' => $quota,
                'usagePercent' => $usagePercent,
                'warningExceeded' => $warningExceeded,
                'quotaFormatted' => $quotaFormatted,
            ];
        }

        return $result;
    }

    /**
     * Создание нового хранилища.
     */
    #[Route('/new', name: 'admin_storage_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new Storage());
        }

        return $this->render('admin/storage/form.html.twig', [
            'storage' => new Storage(),
            'types' => Storage::VALID_TYPES,
        ]);
    }

    /**
     * Редактирование хранилища.
     */
    #[Route('/{id}/edit', name: 'admin_storage_edit')]
    public function edit(Request $request, Storage $storage): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $storage);
        }

        return $this->render('admin/storage/form.html.twig', [
            'storage' => $storage,
            'types' => Storage::VALID_TYPES,
        ]);
    }


    /**
     * Удаление хранилища.
     */
    #[Route('/{id}/delete', name: 'admin_storage_delete', methods: ['POST'])]
    public function delete(Storage $storage): Response
    {
        if ($storage->isDefault()) {
            $this->addFlash('error', 'Нельзя удалить хранилище по умолчанию');
            return $this->redirectToRoute('admin_storage');
        }

        $this->em->remove($storage);
        $this->em->flush();
        
        $this->addFlash('success', 'Хранилище удалено');
        return $this->redirectToRoute('admin_storage');
    }

    /**
     * Тестирование подключения к хранилищу.
     * Requirements 1.6: WHEN an administrator tests storage connection 
     * THEN the System SHALL attempt to connect and report success or detailed error message
     */
    #[Route('/{id}/test', name: 'admin_storage_test', methods: ['POST'])]
    public function test(Storage $storage): JsonResponse
    {
        try {
            $adapter = $this->storageManager->getAdapter($storage);
            $result = $adapter->testConnection();
            
            return new JsonResponse([
                'success' => $result->success,
                'message' => $result->message,
                'latency' => $result->latencyMs,
                'serverInfo' => $result->serverInfo,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Тестирование подключения для новой конфигурации (без сохранения).
     */
    #[Route('/test-config', name: 'admin_storage_test_config', methods: ['POST'])]
    public function testConfig(Request $request): JsonResponse
    {
        $storage = new Storage();
        $this->populateStorageFromRequest($request, $storage);
        
        // Валидация конфигурации
        $errors = $this->configValidator->validate($storage);
        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибки валидации: ' . implode(', ', $errors),
                'errors' => $errors,
            ]);
        }

        try {
            $adapter = $this->storageManager->getAdapter($storage);
            $result = $adapter->testConnection();
            
            return new JsonResponse([
                'success' => $result->success,
                'message' => $result->message,
                'latency' => $result->latencyMs,
                'serverInfo' => $result->serverInfo,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ]);
        }
    }


    /**
     * Установка хранилища по умолчанию.
     * Requirements 1.7: WHEN an administrator sets a storage as default 
     * THEN the System SHALL use this storage for all new video uploads
     */
    #[Route('/{id}/set-default', name: 'admin_storage_set_default', methods: ['POST'])]
    public function setDefault(Storage $storage): Response
    {
        if (!$storage->isEnabled()) {
            $this->addFlash('error', 'Нельзя установить отключённое хранилище по умолчанию');
            return $this->redirectToRoute('admin_storage');
        }

        // Сбрасываем флаг default у всех хранилищ
        $this->storageRepository->createQueryBuilder('s')
            ->update()
            ->set('s.isDefault', ':false')
            ->setParameter('false', false)
            ->getQuery()
            ->execute();

        // Устанавливаем новое хранилище по умолчанию
        $storage->setIsDefault(true);
        $this->em->flush();
        
        $this->addFlash('success', 'Хранилище "' . $storage->getName() . '" установлено по умолчанию');
        return $this->redirectToRoute('admin_storage');
    }

    /**
     * Включение/отключение хранилища.
     * Requirements 1.8: WHEN an administrator disables a storage 
     * THEN the System SHALL prevent new uploads to this storage while keeping existing files accessible
     */
    #[Route('/{id}/toggle', name: 'admin_storage_toggle', methods: ['POST'])]
    public function toggle(Storage $storage): Response
    {
        if ($storage->isDefault() && $storage->isEnabled()) {
            $this->addFlash('error', 'Нельзя отключить хранилище по умолчанию');
            return $this->redirectToRoute('admin_storage');
        }

        $storage->setIsEnabled(!$storage->isEnabled());
        $this->em->flush();
        
        $status = $storage->isEnabled() ? 'включено' : 'отключено';
        $this->addFlash('success', 'Хранилище "' . $storage->getName() . '" ' . $status);
        return $this->redirectToRoute('admin_storage');
    }

    /**
     * Обработка сохранения хранилища.
     * Requirements 1.5: WHEN an administrator saves storage configuration 
     * THEN the System SHALL validate connection parameters before saving
     */
    private function handleSave(Request $request, Storage $storage): Response
    {
        $this->populateStorageFromRequest($request, $storage);
        
        // Валидация конфигурации
        $errors = $this->configValidator->validate($storage);
        if (!empty($errors)) {
            foreach ($errors as $message) {
                $this->addFlash('error', $message);
            }
            return $this->render('admin/storage/form.html.twig', [
                'storage' => $storage,
                'types' => Storage::VALID_TYPES,
                'errors' => $errors,
            ]);
        }

        $this->em->persist($storage);
        $this->em->flush();
        
        $this->addFlash('success', 'Хранилище сохранено');
        return $this->redirectToRoute('admin_storage');
    }


    /**
     * Заполняет сущность Storage данными из запроса.
     */
    private function populateStorageFromRequest(Request $request, Storage $storage): void
    {
        $storage->setName((string) $request->request->get('name', ''));
        $storage->setType((string) $request->request->get('type', Storage::TYPE_LOCAL));
        
        $type = $storage->getType();
        $config = [];

        // Общие поля для FTP и SFTP
        if (\in_array($type, [Storage::TYPE_FTP, Storage::TYPE_SFTP], true)) {
            $config['host'] = (string) $request->request->get('host', '');
            $config['port'] = (int) $request->request->get('port', $type === Storage::TYPE_FTP ? 21 : 22);
            $config['username'] = (string) $request->request->get('username', '');
            $config['basePath'] = (string) $request->request->get('basePath', '/');
        }

        // Специфичные поля для FTP
        if ($type === Storage::TYPE_FTP) {
            $config['password'] = (string) $request->request->get('password', '');
            $config['passive'] = $request->request->get('passive') === '1';
            $config['ssl'] = $request->request->get('ssl') === '1';
        }

        // Специфичные поля для SFTP
        if ($type === Storage::TYPE_SFTP) {
            $config['authType'] = (string) $request->request->get('authType', 'password');
            $config['password'] = (string) $request->request->get('password', '');
            $config['privateKey'] = (string) $request->request->get('privateKey', '');
            $config['privateKeyPassphrase'] = (string) $request->request->get('privateKeyPassphrase', '');
        }

        // Поля для HTTP
        if ($type === Storage::TYPE_HTTP) {
            $config['baseUrl'] = (string) $request->request->get('baseUrl', '');
            $config['uploadEndpoint'] = (string) $request->request->get('uploadEndpoint', '');
            $config['deleteEndpoint'] = (string) $request->request->get('deleteEndpoint', '');
            $config['authToken'] = (string) $request->request->get('authToken', '');
            $config['authHeader'] = (string) $request->request->get('authHeader', 'Authorization');
        }

        // Поля для Local
        if ($type === Storage::TYPE_LOCAL) {
            $config['basePath'] = (string) $request->request->get('basePath', 'public/media');
            $config['baseUrl'] = (string) $request->request->get('localBaseUrl', '/media');
        }

        // Поля для S3 / BunnyCDN
        if ($type === Storage::TYPE_S3) {
            $config['endpoint'] = (string) $request->request->get('s3Endpoint', 'https://storage.bunnycdn.com');
            $config['region'] = (string) $request->request->get('s3Region', 'de');
            $config['bucket'] = (string) $request->request->get('s3Bucket', '');
            $config['accessKey'] = (string) $request->request->get('s3AccessKey', '');
            $config['secretKey'] = (string) $request->request->get('s3SecretKey', '');
            $config['cdnUrl'] = (string) $request->request->get('s3CdnUrl', '');
            $config['pathStyleEndpoint'] = $request->request->get('s3PathStyle') === '1';
        }

        $storage->setConfig($config);
    }

    /**
     * Страница миграции файлов между хранилищами.
     * Requirements 4.1: WHEN an administrator initiates migration 
     * THEN the System SHALL allow selecting source and destination storages
     * Requirements 4.5: WHEN migration completes THEN the System SHALL provide 
     * a summary report with success/failure counts
     */
    #[Route('/migration', name: 'admin_storage_migration')]
    public function migration(): Response
    {
        $storages = $this->storageRepository->findEnabled();
        
        // Подсчитываем количество файлов для каждого хранилища
        $storageStats = [];
        foreach ($storages as $storage) {
            $fileCount = $this->videoFileRepository->count(['storage' => $storage]);
            $storageStats[$storage->getId()] = [
                'storage' => $storage,
                'fileCount' => $fileCount,
            ];
        }
        
        // Файлы без хранилища (локальные)
        $localFileCount = $this->videoFileRepository->count(['storage' => null]);
        
        // Получаем активные и завершённые миграции для отображения
        $activeMigrations = $this->migrationReportService->getActiveMigrations();
        $recentMigrations = $this->migrationReportService->getRecentCompletedMigrations(5);
        
        return $this->render('admin/storage/migration.html.twig', [
            'storages' => $storages,
            'storageStats' => $storageStats,
            'localFileCount' => $localFileCount,
            'activeMigrations' => $activeMigrations,
            'recentMigrations' => $recentMigrations,
        ]);
    }

    /**
     * Получение количества файлов для миграции.
     */
    #[Route('/migration/count', name: 'admin_storage_migration_count', methods: ['POST'])]
    public function migrationCount(Request $request): JsonResponse
    {
        $sourceId = $request->request->get('source');
        $destinationId = $request->request->get('destination');
        
        if ($sourceId === $destinationId) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Источник и назначение должны быть разными',
            ]);
        }
        
        // Определяем источник
        $source = null;
        if ($sourceId !== 'local' && $sourceId !== null) {
            $source = $this->storageRepository->find((int) $sourceId);
            if (!$source) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Хранилище-источник не найдено',
                ]);
            }
        }
        
        // Определяем назначение
        $destination = null;
        if ($destinationId !== 'local' && $destinationId !== null) {
            $destination = $this->storageRepository->find((int) $destinationId);
            if (!$destination) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Хранилище-назначение не найдено',
                ]);
            }
            if (!$destination->isEnabled()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Хранилище-назначение отключено',
                ]);
            }
        }
        
        // Подсчитываем файлы
        $fileCount = $this->videoFileRepository->count(['storage' => $source]);
        
        return new JsonResponse([
            'success' => true,
            'fileCount' => $fileCount,
            'sourceName' => $source ? $source->getName() : 'Локальное хранилище',
            'destinationName' => $destination ? $destination->getName() : 'Локальное хранилище',
        ]);
    }

    /**
     * Запуск миграции файлов между хранилищами.
     * 
     * Requirements 4.2: WHEN migration is started THEN the System SHALL 
     * queue migration jobs for each video file
     * Requirements 4.5: WHEN migration completes THEN the System SHALL provide 
     * a summary report with success/failure counts
     */
    #[Route('/migration/start', name: 'admin_storage_migration_start', methods: ['POST'])]
    public function migrationStart(Request $request): JsonResponse
    {
        $sourceId = $request->request->get('source');
        $destinationId = $request->request->get('destination');
        
        // Валидация: источник и назначение должны быть разными
        if ($sourceId === $destinationId) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Источник и назначение должны быть разными',
            ]);
        }
        
        // Определяем источник
        $source = null;
        if ($sourceId !== 'local' && $sourceId !== null && $sourceId !== '') {
            $source = $this->storageRepository->find((int) $sourceId);
            if (!$source) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Хранилище-источник не найдено',
                ]);
            }
        }
        
        // Определяем назначение
        $destination = null;
        $destinationStorageId = 0; // 0 означает локальное хранилище
        
        if ($destinationId !== 'local' && $destinationId !== null && $destinationId !== '') {
            $destination = $this->storageRepository->find((int) $destinationId);
            if (!$destination) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Хранилище-назначение не найдено',
                ]);
            }
            if (!$destination->isEnabled()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Хранилище-назначение отключено',
                ]);
            }
            $destinationStorageId = $destination->getId();
        }
        
        // Получаем ID всех файлов для миграции
        $fileIds = $this->videoFileRepository->findIdsByStorage($source);
        
        if (empty($fileIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Нет файлов для миграции',
            ]);
        }
        
        $sourceName = $source ? $source->getName() : 'Локальное хранилище';
        $destinationName = $destination ? $destination->getName() : 'Локальное хранилище';
        
        // Создаём отчёт о миграции
        // Requirements 4.5: provide a summary report with success/failure counts
        $migrationId = $this->migrationReportService->generateMigrationId();
        $this->migrationReportService->createReport(
            $migrationId,
            count($fileIds),
            $sourceName,
            $destinationName
        );
        
        // Ставим задачи миграции в очередь
        // Requirements 4.2: queue migration jobs for each video file
        $queuedCount = 0;
        foreach ($fileIds as $fileId) {
            $message = new MigrateFileMessage(
                videoFileId: $fileId,
                destinationStorageId: $destinationStorageId,
                migrationId: $migrationId
            );
            $this->messageBus->dispatch($message);
            $queuedCount++;
        }
        
        return new JsonResponse([
            'success' => true,
            'message' => "Миграция запущена: {$queuedCount} файлов поставлено в очередь",
            'queuedCount' => $queuedCount,
            'sourceName' => $sourceName,
            'destinationName' => $destinationName,
            'migrationId' => $migrationId,
        ]);
    }

    /**
     * Получение статуса миграции.
     * 
     * Requirements 4.5: WHEN migration completes THEN the System SHALL provide 
     * a summary report with success/failure counts
     */
    #[Route('/migration/{migrationId}/status', name: 'admin_storage_migration_status', methods: ['GET'])]
    public function migrationStatus(string $migrationId): JsonResponse
    {
        $summary = $this->migrationReportService->getSummary($migrationId);
        
        return new JsonResponse($summary);
    }

    /**
     * Получение списка ошибок миграции.
     */
    #[Route('/migration/{migrationId}/failures', name: 'admin_storage_migration_failures', methods: ['GET'])]
    public function migrationFailures(string $migrationId): JsonResponse
    {
        $failures = $this->migrationReportService->getFailures($migrationId);
        
        return new JsonResponse([
            'success' => true,
            'failures' => $failures,
            'count' => count($failures),
        ]);
    }
}
