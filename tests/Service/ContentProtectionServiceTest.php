<?php

namespace App\Tests\Service;

use App\Repository\ContentProtectionSettingRepository;
use App\Service\ContentProtectionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ContentProtectionServiceTest extends TestCase
{
    private ContentProtectionService $service;
    private ContentProtectionSettingRepository $repository;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ContentProtectionSettingRepository::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        
        $this->service = new ContentProtectionService(
            $this->repository,
            $this->requestStack
        );
    }

    public function testHotlinkProtectionDisabled(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['hotlink_protection_enabled', false, false],
                ['user_agent_filtering_enabled', false, false],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $errors = $this->service->validateRequest($request);

        $this->assertEmpty($errors);
    }

    public function testHotlinkProtectionBlocksNoReferer(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['hotlink_protection_enabled', false, true],
                ['user_agent_filtering_enabled', false, false],
                ['allowed_domains', [], []],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $errors = $this->service->validateRequest($request);

        $this->assertContains('Invalid referer', $errors);
    }

    public function testHotlinkProtectionAllowsSameHost(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['hotlink_protection_enabled', false, true],
                ['user_agent_filtering_enabled', false, false],
                ['allowed_domains', [], []],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $request->headers->set('referer', 'http://localhost/video/123');
        $errors = $this->service->validateRequest($request);

        $this->assertEmpty($errors);
    }

    public function testUserAgentFilteringBlocksWget(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['hotlink_protection_enabled', false, false],
                ['user_agent_filtering_enabled', false, true],
                ['blocked_user_agents', [], []],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $request->headers->set('user-agent', 'Wget/1.20.3');
        $errors = $this->service->validateRequest($request);

        $this->assertContains('Blocked user agent', $errors);
    }

    public function testUserAgentFilteringBlocksCurl(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['hotlink_protection_enabled', false, false],
                ['user_agent_filtering_enabled', false, true],
                ['blocked_user_agents', [], []],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $request->headers->set('user-agent', 'curl/7.68.0');
        $errors = $this->service->validateRequest($request);

        $this->assertContains('Blocked user agent', $errors);
    }

    public function testUserAgentFilteringBlocksYoutubeDl(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['hotlink_protection_enabled', false, false],
                ['user_agent_filtering_enabled', false, true],
                ['blocked_user_agents', [], []],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $request->headers->set('user-agent', 'youtube-dl/2021.12.17');
        $errors = $this->service->validateRequest($request);

        $this->assertContains('Blocked user agent', $errors);
    }

    public function testUserAgentFilteringAllowsBrowser(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['hotlink_protection_enabled', false, false],
                ['user_agent_filtering_enabled', false, true],
                ['blocked_user_agents', [], []],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $request->headers->set('user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $errors = $this->service->validateRequest($request);

        $this->assertEmpty($errors);
    }

    public function testSignedUrlsDisabled(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['signed_urls_enabled', false, false],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $result = $this->service->validateSignedUrl($request, '/secure-media/videos/test.mp4');

        $this->assertTrue($result);
    }

    public function testSignedUrlsRejectsWithoutToken(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['signed_urls_enabled', false, true],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4');
        $result = $this->service->validateSignedUrl($request, '/secure-media/videos/test.mp4');

        $this->assertFalse($result);
    }

    public function testSignedUrlsRejectsExpiredToken(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['signed_urls_enabled', false, true],
            ]);

        $request = Request::create('/secure-media/videos/test.mp4?token=abc&expires=' . (time() - 100));
        $result = $this->service->validateSignedUrl($request, '/secure-media/videos/test.mp4');

        $this->assertFalse($result);
    }

    public function testGetRateLimitPerHour(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['rate_limit_per_hour', 100, 500],
            ]);

        $result = $this->service->getRateLimitPerHour();

        $this->assertEquals(500, $result);
    }

    public function testWatermarkSettings(): void
    {
        $this->repository->method('getValue')
            ->willReturnMap([
                ['watermark_enabled', false, true],
                ['watermark_text', '', 'RexTube'],
                ['watermark_position', 'bottom-right', 'top-left'],
                ['watermark_opacity', 50, 75],
            ]);

        $this->assertTrue($this->service->isWatermarkEnabled());
        $this->assertEquals('RexTube', $this->service->getWatermarkText());
        $this->assertEquals('top-left', $this->service->getWatermarkPosition());
        $this->assertEquals(75, $this->service->getWatermarkOpacity());
    }
}
