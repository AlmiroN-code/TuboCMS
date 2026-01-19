<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Service\SignedUrlService;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Property-based tests for signed URL generation.
 * 
 * **Feature: remote-storage, Property 7: Signed URLs contain signature and expiration**
 * **Validates: Requirements 3.4**
 * 
 * Property: For any signed URL generated with expiration time T, the URL SHALL contain 
 * a signature parameter and expire parameter with value T.
 */
class SignedUrlPropertyTest extends TestCase
{
    use TestTrait;

    private const SECRET_KEY = 'test-secret-key-for-property-testing';

    /**
     * Property 7: Signed URL contains signature parameter.
     * 
     * For any path and expiration time, the generated signed URL SHALL contain
     * a 'signature' parameter.
     */
    public function testSignedUrlContainsSignatureParameter(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements([
                '/video.mp4',
                '/videos/test.mp4',
                '/media/2024/01/video.mp4',
                '/path/to/file.mp4',
                '/a.mp4',
            ]),
            Generator\choose(60, 86400)
        )->then(function (string $path, int $expiresIn): void {
            $service = $this->createSignedUrlService();
            $signedUrl = $service->generateSignedUrl($path, $expiresIn);
            
            $this->assertStringContainsString(
                'signature=',
                $signedUrl,
                'Signed URL must contain signature parameter'
            );
        });
    }

    /**
     * Property 7: Signed URL contains expires parameter.
     * 
     * For any path and expiration time T, the generated signed URL SHALL contain
     * an 'expires' parameter.
     */
    public function testSignedUrlContainsExpiresParameter(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements([
                '/video.mp4',
                '/videos/test.mp4',
                '/media/2024/01/video.mp4',
                '/path/to/file.mp4',
                '/a.mp4',
            ]),
            Generator\choose(60, 86400)
        )->then(function (string $path, int $expiresIn): void {
            $service = $this->createSignedUrlService();
            $signedUrl = $service->generateSignedUrl($path, $expiresIn);
            
            $this->assertStringContainsString(
                'expires=',
                $signedUrl,
                'Signed URL must contain expires parameter'
            );
        });
    }

    /**
     * Property 7: Expires parameter value matches expected expiration time.
     * 
     * For any signed URL generated with expiration time T, the expires parameter
     * SHALL have a value equal to current_time + T (within reasonable tolerance).
     */
    public function testExpiresParameterMatchesExpirationTime(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements([
                '/video.mp4',
                '/videos/test.mp4',
                '/media/2024/01/video.mp4',
            ]),
            Generator\choose(60, 86400)
        )->then(function (string $path, int $expiresIn): void {
            $beforeTime = \time();
            $service = $this->createSignedUrlService();
            $signedUrl = $service->generateSignedUrl($path, $expiresIn);
            $afterTime = \time();
            
            $parsed = $service->parseSignedUrl($signedUrl);
            $actualExpires = $parsed['expires'];
            
            $minExpected = $beforeTime + $expiresIn;
            $maxExpected = $afterTime + $expiresIn;
            
            $this->assertGreaterThanOrEqual(
                $minExpected,
                $actualExpires,
                'Expires timestamp should be at least current_time + expiresIn'
            );
            $this->assertLessThanOrEqual(
                $maxExpected,
                $actualExpires,
                'Expires timestamp should be at most current_time + expiresIn'
            );
        });
    }

    /**
     * Property 7: Signature is non-empty.
     * 
     * For any signed URL, the signature parameter SHALL be a non-empty string.
     */
    public function testSignatureIsNonEmpty(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements([
                '/video.mp4',
                '/videos/test.mp4',
                '/media/2024/01/video.mp4',
            ]),
            Generator\choose(60, 86400)
        )->then(function (string $path, int $expiresIn): void {
            $service = $this->createSignedUrlService();
            $signedUrl = $service->generateSignedUrl($path, $expiresIn);
            
            $parsed = $service->parseSignedUrl($signedUrl);
            
            $this->assertNotEmpty(
                $parsed['signature'],
                'Signature must be non-empty'
            );
        });
    }

    /**
     * Property 7: Valid signed URL can be verified.
     * 
     * For any signed URL generated by the service, verification SHALL succeed
     * when the URL has not expired.
     */
    public function testValidSignedUrlCanBeVerified(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements([
                '/video.mp4',
                '/videos/test.mp4',
                '/media/2024/01/video.mp4',
                '/path/to/file.mp4',
            ]),
            Generator\choose(60, 86400)
        )->then(function (string $path, int $expiresIn): void {
            $service = $this->createSignedUrlService();
            $signedUrl = $service->generateSignedUrl($path, $expiresIn);
            
            $parsed = $service->parseSignedUrl($signedUrl);
            
            $isValid = $service->verifySignedUrl(
                $parsed['path'],
                $parsed['expires'],
                $parsed['signature'],
                $parsed['storage_id']
            );
            
            $this->assertTrue(
                $isValid,
                'Valid signed URL should pass verification'
            );
        });
    }

    /**
     * Property 7: Signed URL with storage ID contains storage parameter.
     * 
     * When generating a signed URL with storage ID, the URL SHALL contain
     * a 'storage' parameter with the correct value.
     */
    public function testSignedUrlWithStorageIdContainsStorageParameter(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements([
                '/video.mp4',
                '/videos/test.mp4',
                '/media/2024/01/video.mp4',
            ]),
            Generator\choose(60, 86400),
            Generator\choose(1, 1000)
        )->then(function (string $path, int $expiresIn, int $storageId): void {
            $service = $this->createSignedUrlService();
            $signedUrl = $service->generateSignedUrl($path, $expiresIn, $storageId);
            
            $this->assertStringContainsString(
                'storage=' . $storageId,
                $signedUrl,
                'Signed URL with storage ID must contain storage parameter'
            );
            
            $parsed = $service->parseSignedUrl($signedUrl);
            $this->assertEquals(
                $storageId,
                $parsed['storage_id'],
                'Parsed storage ID must match original'
            );
        });
    }

    /**
     * Property 7: Different paths produce different signatures.
     * 
     * For any two different paths with the same expiration, the signatures
     * SHALL be different.
     */
    public function testDifferentPathsProduceDifferentSignatures(): void
    {
        $this->limitTo(100)->forAll(
            Generator\choose(60, 86400)
        )->then(function (int $expiresIn): void {
            $path1 = '/videos/video1.mp4';
            $path2 = '/videos/video2.mp4';
            
            $service = $this->createSignedUrlService();
            
            $url1 = $service->generateSignedUrl($path1, $expiresIn);
            $url2 = $service->generateSignedUrl($path2, $expiresIn);
            
            $parsed1 = $service->parseSignedUrl($url1);
            $parsed2 = $service->parseSignedUrl($url2);
            
            $this->assertNotEquals(
                $parsed1['signature'],
                $parsed2['signature'],
                'Different paths should produce different signatures'
            );
        });
    }

    /**
     * Property 7: Tampered signature fails verification.
     * 
     * For any signed URL, modifying the signature SHALL cause verification to fail.
     */
    public function testTamperedSignatureFailsVerification(): void
    {
        $this->limitTo(100)->forAll(
            Generator\elements([
                '/video.mp4',
                '/videos/test.mp4',
                '/media/2024/01/video.mp4',
            ]),
            Generator\choose(60, 86400)
        )->then(function (string $path, int $expiresIn): void {
            $service = $this->createSignedUrlService();
            $signedUrl = $service->generateSignedUrl($path, $expiresIn);
            
            $parsed = $service->parseSignedUrl($signedUrl);
            
            // Tamper with signature
            $tamperedSignature = $parsed['signature'] . 'tampered';
            
            $isValid = $service->verifySignedUrl(
                $parsed['path'],
                $parsed['expires'],
                $tamperedSignature,
                $parsed['storage_id']
            );
            
            $this->assertFalse(
                $isValid,
                'Tampered signature should fail verification'
            );
        });
    }

    /**
     * Creates SignedUrlService instance for testing.
     */
    private function createSignedUrlService(): SignedUrlService
    {
        return new SignedUrlService(
            self::SECRET_KEY,
            new NullLogger()
        );
    }
}
