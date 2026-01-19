<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use App\Validator\StorageConfigValidator;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for Storage configuration validation.
 * 
 * **Feature: remote-storage, Property 1: Storage validation requires all mandatory fields**
 * **Validates: Requirements 1.2, 1.3, 1.4**
 * 
 * Property: For any FTP/SFTP/HTTP storage configuration, validation SHALL fail 
 * if any of the mandatory fields is empty or missing.
 */
class StorageConfigValidationPropertyTest extends TestCase
{
    use TestTrait;

    private StorageConfigValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new StorageConfigValidator();
    }

    /**
     * Property 1.2: FTP storage requires host, port, username, password, basePath.
     * 
     * For any FTP storage with missing mandatory field, validation SHALL fail.
     */
    public function testFtpStorageWithMissingFieldFailsValidation(): void
    {
        $requiredFields = ['host', 'port', 'username', 'password', 'basePath'];

        $this->forAll(
            Generator\elements($requiredFields)
        )->then(function (string $missingField) use ($requiredFields): void {
            $config = [
                'host' => 'ftp.example.com',
                'port' => 21,
                'username' => 'user',
                'password' => 'pass',
                'basePath' => '/videos',
            ];

            // Удаляем одно обязательное поле
            unset($config[$missingField]);

            $storage = new Storage();
            $storage->setName('Test FTP');
            $storage->setType(Storage::TYPE_FTP);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertArrayHasKey(
                $missingField,
                $errors,
                sprintf('FTP storage without "%s" should fail validation', $missingField)
            );
        });
    }

    /**
     * Property 1.2: FTP storage with empty field values fails validation.
     * 
     * For any FTP storage with empty mandatory field value, validation SHALL fail.
     */
    public function testFtpStorageWithEmptyFieldFailsValidation(): void
    {
        $requiredFields = ['host', 'username', 'password', 'basePath'];

        $this->forAll(
            Generator\elements($requiredFields),
            Generator\elements(['', ' ', '  ', null])
        )->then(function (string $emptyField, $emptyValue): void {
            $config = [
                'host' => 'ftp.example.com',
                'port' => 21,
                'username' => 'user',
                'password' => 'pass',
                'basePath' => '/videos',
            ];

            // Устанавливаем пустое значение
            $config[$emptyField] = $emptyValue;

            $storage = new Storage();
            $storage->setName('Test FTP');
            $storage->setType(Storage::TYPE_FTP);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertArrayHasKey(
                $emptyField,
                $errors,
                sprintf('FTP storage with empty "%s" should fail validation', $emptyField)
            );
        });
    }

    /**
     * Property 1.2: Valid FTP configuration passes validation.
     * 
     * For any FTP storage with all mandatory fields filled, validation SHALL pass.
     */
    public function testValidFtpStoragePassesValidation(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\choose(1, 65535),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $host, int $port, string $username, string $password, string $basePath): void {
            $config = [
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'basePath' => $basePath,
            ];

            $storage = new Storage();
            $storage->setName('Test FTP');
            $storage->setType(Storage::TYPE_FTP);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertEmpty(
                $errors,
                sprintf('Valid FTP config should pass. Errors: %s', json_encode($errors))
            );
        });
    }

    /**
     * Property 1.3: SFTP storage requires host, port, username, authType, basePath.
     * 
     * For any SFTP storage with missing mandatory field, validation SHALL fail.
     */
    public function testSftpStorageWithMissingFieldFailsValidation(): void
    {
        $requiredFields = ['host', 'port', 'username', 'authType', 'basePath'];

        $this->forAll(
            Generator\elements($requiredFields)
        )->then(function (string $missingField) use ($requiredFields): void {
            $config = [
                'host' => 'sftp.example.com',
                'port' => 22,
                'username' => 'user',
                'authType' => 'password',
                'password' => 'pass',
                'basePath' => '/videos',
            ];

            unset($config[$missingField]);

            $storage = new Storage();
            $storage->setName('Test SFTP');
            $storage->setType(Storage::TYPE_SFTP);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertArrayHasKey(
                $missingField,
                $errors,
                sprintf('SFTP storage without "%s" should fail validation', $missingField)
            );
        });
    }

    /**
     * Property 1.3: SFTP with password auth requires password field.
     */
    public function testSftpPasswordAuthRequiresPassword(): void
    {
        $storage = new Storage();
        $storage->setName('Test SFTP');
        $storage->setType(Storage::TYPE_SFTP);
        $storage->setConfig([
            'host' => 'sftp.example.com',
            'port' => 22,
            'username' => 'user',
            'authType' => 'password',
            'basePath' => '/videos',
            // password отсутствует
        ]);

        $errors = $this->validator->validate($storage);

        $this->assertArrayHasKey('password', $errors);
    }

    /**
     * Property 1.3: SFTP with key auth requires privateKey field.
     */
    public function testSftpKeyAuthRequiresPrivateKey(): void
    {
        $storage = new Storage();
        $storage->setName('Test SFTP');
        $storage->setType(Storage::TYPE_SFTP);
        $storage->setConfig([
            'host' => 'sftp.example.com',
            'port' => 22,
            'username' => 'user',
            'authType' => 'key',
            'basePath' => '/videos',
            // privateKey отсутствует
        ]);

        $errors = $this->validator->validate($storage);

        $this->assertArrayHasKey('privateKey', $errors);
    }

    /**
     * Property 1.3: Valid SFTP configuration with password passes validation.
     */
    public function testValidSftpPasswordStoragePassesValidation(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\choose(1, 65535),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $host, int $port, string $username, string $password, string $basePath): void {
            $config = [
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'authType' => 'password',
                'password' => $password,
                'basePath' => $basePath,
            ];

            $storage = new Storage();
            $storage->setName('Test SFTP');
            $storage->setType(Storage::TYPE_SFTP);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertEmpty(
                $errors,
                sprintf('Valid SFTP config should pass. Errors: %s', json_encode($errors))
            );
        });
    }

    /**
     * Property 1.4: HTTP storage requires baseUrl, authToken, uploadEndpoint.
     * 
     * For any HTTP storage with missing mandatory field, validation SHALL fail.
     */
    public function testHttpStorageWithMissingFieldFailsValidation(): void
    {
        $requiredFields = ['baseUrl', 'authToken', 'uploadEndpoint'];

        $this->forAll(
            Generator\elements($requiredFields)
        )->then(function (string $missingField) use ($requiredFields): void {
            $config = [
                'baseUrl' => 'https://storage.example.com',
                'authToken' => 'secret-token',
                'uploadEndpoint' => '/api/upload',
            ];

            unset($config[$missingField]);

            $storage = new Storage();
            $storage->setName('Test HTTP');
            $storage->setType(Storage::TYPE_HTTP);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertArrayHasKey(
                $missingField,
                $errors,
                sprintf('HTTP storage without "%s" should fail validation', $missingField)
            );
        });
    }

    /**
     * Property 1.4: HTTP storage with empty field values fails validation.
     */
    public function testHttpStorageWithEmptyFieldFailsValidation(): void
    {
        $requiredFields = ['baseUrl', 'authToken', 'uploadEndpoint'];

        $this->forAll(
            Generator\elements($requiredFields),
            Generator\elements(['', ' ', '  ', null])
        )->then(function (string $emptyField, $emptyValue): void {
            $config = [
                'baseUrl' => 'https://storage.example.com',
                'authToken' => 'secret-token',
                'uploadEndpoint' => '/api/upload',
            ];

            $config[$emptyField] = $emptyValue;

            $storage = new Storage();
            $storage->setName('Test HTTP');
            $storage->setType(Storage::TYPE_HTTP);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertArrayHasKey(
                $emptyField,
                $errors,
                sprintf('HTTP storage with empty "%s" should fail validation', $emptyField)
            );
        });
    }

    /**
     * Property: Port validation for FTP/SFTP.
     * 
     * For any FTP/SFTP storage with invalid port, validation SHALL fail.
     */
    public function testInvalidPortFailsValidation(): void
    {
        $this->forAll(
            Generator\elements([Storage::TYPE_FTP, Storage::TYPE_SFTP]),
            Generator\oneOf(
                Generator\choose(-1000, 0),
                Generator\choose(65536, 100000)
            )
        )->then(function (string $type, int $invalidPort): void {
            $config = [
                'host' => 'example.com',
                'port' => $invalidPort,
                'username' => 'user',
                'password' => 'pass',
                'basePath' => '/videos',
            ];

            if ($type === Storage::TYPE_SFTP) {
                $config['authType'] = 'password';
            }

            $storage = new Storage();
            $storage->setName('Test Storage');
            $storage->setType($type);
            $storage->setConfig($config);

            $errors = $this->validator->validate($storage);

            $this->assertArrayHasKey(
                'port',
                $errors,
                sprintf('Port %d should fail validation for %s', $invalidPort, $type)
            );
        });
    }

    /**
     * Property: Local storage requires no configuration fields.
     */
    public function testLocalStorageRequiresNoConfig(): void
    {
        $storage = new Storage();
        $storage->setName('Local Storage');
        $storage->setType(Storage::TYPE_LOCAL);
        $storage->setConfig([]);

        $errors = $this->validator->validate($storage);

        $this->assertEmpty($errors, 'Local storage should not require any config fields');
    }
}
