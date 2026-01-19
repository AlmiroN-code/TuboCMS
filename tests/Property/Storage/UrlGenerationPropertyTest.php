<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use App\Entity\VideoFile;
use App\Repository\StorageRepository;
use App\Service\StorageManager;
use App\Storage\Adapter\FtpStorageAdapter;
use App\Storage\Adapter\HttpStorageAdapter;
use App\Storage\Adapter\LocalStorageAdapter;
use App\Storage\Adapter\SftpStorageAdapter;
use App\Storage\Factory\StorageAdapterFactoryInterface;
use App\Storage\StorageAdapterInterface;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Property-based tests for URL generation based on storage type.
 * 
 * **Feature: remote-storage, Property 6: URL generation matches storage type**
 * **Validates: Requirements 3.1, 3.3**
 * 
 * Property: For any VideoFile with associated Storage, getFileUrl() SHALL return 
 * a URL appropriate for that storage type (proxy for FTP/SFTP, direct for HTTP).
 */
class UrlGenerationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 6: FTP storage returns proxy URL.
     * 
     * For any VideoFile stored on FTP, the URL SHALL start with /storage/proxy/
     */
    public function testFtpStorageReturnsProxyUrl(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => \strlen(\trim($s)) > 0 && !\str_contains($s, "\0"),
                Generator\string()
            )
        )->then(function (string $remotePath): void {
            $remotePath = \ltrim($remotePath, '/');
            if (empty($remotePath)) {
                $remotePath = 'test/video.mp4';
            }
            
            $storage = $this->createFtpStorage();
            $videoFile = $this->createVideoFile($storage, $remotePath);
            
            $storageManager = $this->createStorageManagerWithAdapter(
                $storage,
                new FtpStorageAdapter(
                    host: 'ftp.example.com',
                    port: 21,
                    username: 'user',
                    password: 'pass',
                    basePath: '/videos'
                )
            );
            
            $url = $storageManager->getFileUrl($videoFile);
            
            $this->assertStringStartsWith(
                '/storage/proxy/',
                $url,
                'FTP storage URL should start with /storage/proxy/'
            );
        });
    }

    /**
     * Property 6: SFTP storage returns proxy URL.
     * 
     * For any VideoFile stored on SFTP, the URL SHALL start with /storage/proxy/
     */
    public function testSftpStorageReturnsProxyUrl(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => \strlen(\trim($s)) > 0 && !\str_contains($s, "\0"),
                Generator\string()
            )
        )->then(function (string $remotePath): void {
            $remotePath = \ltrim($remotePath, '/');
            if (empty($remotePath)) {
                $remotePath = 'test/video.mp4';
            }
            
            $storage = $this->createSftpStorage();
            $videoFile = $this->createVideoFile($storage, $remotePath);
            
            $storageManager = $this->createStorageManagerWithAdapter(
                $storage,
                new SftpStorageAdapter(
                    host: 'sftp.example.com',
                    port: 22,
                    username: 'user',
                    password: 'pass',
                    privateKey: null,
                    privateKeyPassphrase: null,
                    basePath: '/videos'
                )
            );
            
            $url = $storageManager->getFileUrl($videoFile);
            
            $this->assertStringStartsWith(
                '/storage/proxy/',
                $url,
                'SFTP storage URL should start with /storage/proxy/'
            );
        });
    }

    /**
     * Property 6: HTTP storage returns direct URL.
     * 
     * For any VideoFile stored on HTTP, the URL SHALL be a direct URL to the remote file.
     * Requirement 3.3: WHEN video is stored on Remote Server THEN the System 
     * SHALL return the direct URL to the remote file
     */
    public function testHttpStorageReturnsDirectUrl(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => \strlen(\trim($s)) > 0 && !\str_contains($s, "\0"),
                Generator\string()
            )
        )->then(function (string $remotePath): void {
            $remotePath = \ltrim($remotePath, '/');
            if (empty($remotePath)) {
                $remotePath = 'test/video.mp4';
            }
            
            $baseUrl = 'https://cdn.example.com';
            $storage = $this->createHttpStorage($baseUrl);
            $videoFile = $this->createVideoFile($storage, $remotePath);
            
            $httpClient = $this->createMock(HttpClientInterface::class);
            
            $storageManager = $this->createStorageManagerWithAdapter(
                $storage,
                new HttpStorageAdapter(
                    httpClient: $httpClient,
                    baseUrl: $baseUrl,
                    uploadEndpoint: '/upload',
                    deleteEndpoint: '/delete',
                    authToken: 'test-token'
                )
            );
            
            $url = $storageManager->getFileUrl($videoFile);
            
            $this->assertStringStartsWith(
                $baseUrl,
                $url,
                'HTTP storage URL should start with base URL'
            );
            $this->assertStringNotContainsString(
                '/storage/proxy/',
                $url,
                'HTTP storage URL should NOT contain /storage/proxy/'
            );
        });
    }

    /**
     * Property 6: Local storage returns relative or public URL.
     * 
     * For any VideoFile stored locally, the URL SHALL be a relative path or public URL.
     */
    public function testLocalStorageReturnsRelativeUrl(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => \strlen(\trim($s)) > 0 && !\str_contains($s, "\0"),
                Generator\string()
            )
        )->then(function (string $remotePath): void {
            $remotePath = \ltrim($remotePath, '/');
            if (empty($remotePath)) {
                $remotePath = 'test/video.mp4';
            }
            
            $storage = $this->createLocalStorage();
            $videoFile = $this->createVideoFile($storage, $remotePath);
            
            $storageManager = $this->createStorageManagerWithAdapter(
                $storage,
                new LocalStorageAdapter(
                    basePath: '/var/www/public/media',
                    publicUrl: ''
                )
            );
            
            $url = $storageManager->getFileUrl($videoFile);
            
            // Local storage returns relative path starting with /
            $this->assertStringStartsWith(
                '/',
                $url,
                'Local storage URL should start with /'
            );
            $this->assertStringNotContainsString(
                '/storage/proxy/',
                $url,
                'Local storage URL should NOT contain /storage/proxy/'
            );
        });
    }

    /**
     * Property 6: VideoFile without storage returns local file path.
     * 
     * For any VideoFile without associated Storage, getFileUrl() SHALL return 
     * the local file path.
     */
    public function testVideoFileWithoutStorageReturnsLocalPath(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => \strlen(\trim($s)) > 0 && !\str_contains($s, "\0"),
                Generator\string()
            )
        )->then(function (string $localPath): void {
            $localPath = \ltrim($localPath, '/');
            if (empty($localPath)) {
                $localPath = 'media/videos/test.mp4';
            }
            
            $videoFile = new VideoFile();
            $videoFile->setFile($localPath);
            // No storage set, no remote path
            
            $repository = $this->createMock(StorageRepository::class);
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                []
            );
            
            $url = $storageManager->getFileUrl($videoFile);
            
            $this->assertEquals(
                $localPath,
                $url,
                'VideoFile without storage should return local file path'
            );
        });
    }

    /**
     * Property 6: URL contains remote path.
     * 
     * For any VideoFile with remote storage, the generated URL SHALL contain 
     * the remote path (possibly URL-encoded).
     */
    public function testUrlContainsRemotePath(): void
    {
        $this->forAll(
            Generator\elements(['ftp', 'sftp', 'http', 'local'])
        )->then(function (string $storageType): void {
            $remotePath = 'videos/2024/test-video.mp4';
            
            $storage = match ($storageType) {
                'ftp' => $this->createFtpStorage(),
                'sftp' => $this->createSftpStorage(),
                'http' => $this->createHttpStorage('https://cdn.example.com'),
                'local' => $this->createLocalStorage(),
            };
            
            $videoFile = $this->createVideoFile($storage, $remotePath);
            
            $adapter = match ($storageType) {
                'ftp' => new FtpStorageAdapter('ftp.example.com', 21, 'user', 'pass', '/videos'),
                'sftp' => new SftpStorageAdapter('sftp.example.com', 22, 'user', 'pass', null, null, '/videos'),
                'http' => new HttpStorageAdapter(
                    $this->createMock(HttpClientInterface::class),
                    'https://cdn.example.com',
                    '/upload',
                    '/delete',
                    'token'
                ),
                'local' => new LocalStorageAdapter('/var/www/public/media', ''),
            };
            
            $storageManager = $this->createStorageManagerWithAdapter($storage, $adapter);
            
            $url = $storageManager->getFileUrl($videoFile);
            
            // URL should contain the remote path (or part of it)
            $this->assertStringContainsString(
                'test-video.mp4',
                $url,
                "URL for {$storageType} storage should contain the filename"
            );
        });
    }

    /**
     * Creates FTP storage entity.
     */
    private function createFtpStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('FTP Storage');
        $storage->setType(Storage::TYPE_FTP);
        $storage->setIsEnabled(true);
        $storage->setConfig([
            'host' => 'ftp.example.com',
            'port' => 21,
            'username' => 'user',
            'password' => 'pass',
            'basePath' => '/videos',
        ]);
        
        return $storage;
    }

    /**
     * Creates SFTP storage entity.
     */
    private function createSftpStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('SFTP Storage');
        $storage->setType(Storage::TYPE_SFTP);
        $storage->setIsEnabled(true);
        $storage->setConfig([
            'host' => 'sftp.example.com',
            'port' => 22,
            'username' => 'user',
            'password' => 'pass',
            'basePath' => '/videos',
        ]);
        
        return $storage;
    }

    /**
     * Creates HTTP storage entity.
     */
    private function createHttpStorage(string $baseUrl): Storage
    {
        $storage = new Storage();
        $storage->setName('HTTP Storage');
        $storage->setType(Storage::TYPE_HTTP);
        $storage->setIsEnabled(true);
        $storage->setConfig([
            'baseUrl' => $baseUrl,
            'uploadEndpoint' => '/upload',
            'deleteEndpoint' => '/delete',
            'authToken' => 'test-token',
        ]);
        
        return $storage;
    }

    /**
     * Creates Local storage entity.
     */
    private function createLocalStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('Local Storage');
        $storage->setType(Storage::TYPE_LOCAL);
        $storage->setIsEnabled(true);
        $storage->setConfig([
            'basePath' => '/var/www/public/media',
            'publicUrl' => '',
        ]);
        
        return $storage;
    }

    /**
     * Creates VideoFile with storage and remote path.
     */
    private function createVideoFile(Storage $storage, string $remotePath): VideoFile
    {
        $videoFile = new VideoFile();
        $videoFile->setFile('local/path/video.mp4');
        $videoFile->setStorage($storage);
        $videoFile->setRemotePath($remotePath);
        
        return $videoFile;
    }

    /**
     * Creates StorageManager with specific adapter for storage.
     */
    private function createStorageManagerWithAdapter(
        Storage $storage,
        StorageAdapterInterface $adapter
    ): StorageManager {
        $repository = $this->createMock(StorageRepository::class);
        
        $factory = $this->createMock(StorageAdapterFactoryInterface::class);
        $factory->method('supports')->willReturnCallback(
            fn(Storage $s) => $s->getType() === $storage->getType()
        );
        $factory->method('create')->willReturn($adapter);
        
        return new StorageManager(
            $repository,
            new NullLogger(),
            [$factory]
        );
    }
}
