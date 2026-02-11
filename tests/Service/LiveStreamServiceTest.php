<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\LiveStream;
use App\Entity\User;
use App\Service\LiveStreamService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LiveStreamServiceTest extends KernelTestCase
{
    private LiveStreamService $liveStreamService;
    private $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->liveStreamService = $container->get(LiveStreamService::class);
        $this->em = $container->get('doctrine')->getManager();
    }

    public function testCreateStream(): void
    {
        $user = $this->createTestUser();
        
        $stream = $this->liveStreamService->createStream(
            $user,
            'Test Live Stream',
            'Test Description'
        );

        $this->assertNotNull($stream->getId());
        $this->assertEquals('Test Live Stream', $stream->getTitle());
        $this->assertEquals('Test Description', $stream->getDescription());
        $this->assertSame($user, $stream->getStreamer());
        $this->assertEquals(LiveStream::STATUS_SCHEDULED, $stream->getStatus());
        $this->assertNotNull($stream->getSlug());
    }

    public function testStartStream(): void
    {
        $user = $this->createTestUser();
        $stream = $this->liveStreamService->createStream($user, 'Test Stream');

        $this->assertEquals(LiveStream::STATUS_SCHEDULED, $stream->getStatus());
        
        $this->liveStreamService->startStream($stream);
        
        $this->assertEquals(LiveStream::STATUS_LIVE, $stream->getStatus());
        $this->assertNotNull($stream->getStartedAt());
    }

    public function testEndStream(): void
    {
        $user = $this->createTestUser();
        $stream = $this->liveStreamService->createStream($user, 'Test Stream');
        $this->liveStreamService->startStream($stream);

        $this->assertEquals(LiveStream::STATUS_LIVE, $stream->getStatus());
        
        $this->liveStreamService->endStream($stream);
        
        $this->assertEquals(LiveStream::STATUS_ENDED, $stream->getStatus());
        $this->assertNotNull($stream->getEndedAt());
    }

    public function testIncrementViewer(): void
    {
        $user = $this->createTestUser();
        $stream = $this->liveStreamService->createStream($user, 'Test Stream');

        $this->assertEquals(0, $stream->getViewersCount());
        $this->assertEquals(0, $stream->getTotalViews());
        
        $this->liveStreamService->incrementViewer($stream);
        
        $this->assertEquals(1, $stream->getViewersCount());
        $this->assertEquals(1, $stream->getTotalViews());
    }

    public function testDecrementViewer(): void
    {
        $user = $this->createTestUser();
        $stream = $this->liveStreamService->createStream($user, 'Test Stream');
        $this->liveStreamService->incrementViewer($stream);

        $this->assertEquals(1, $stream->getViewersCount());
        
        $this->liveStreamService->decrementViewer($stream);
        
        $this->assertEquals(0, $stream->getViewersCount());
    }

    public function testGetLiveStreams(): void
    {
        $user = $this->createTestUser();
        
        $stream1 = $this->liveStreamService->createStream($user, 'Stream 1');
        $this->liveStreamService->startStream($stream1);

        $count = $this->liveStreamService->countLiveStreams();
        
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountLiveStreams(): void
    {
        $user = $this->createTestUser();
        
        $initialCount = $this->liveStreamService->countLiveStreams();
        
        $stream = $this->liveStreamService->createStream($user, 'Test Stream');
        $this->liveStreamService->startStream($stream);

        $newCount = $this->liveStreamService->countLiveStreams();
        
        $this->assertEquals($initialCount + 1, $newCount);
    }

    private function createTestUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'livestream@test.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('livestream@test.com');
            $user->setUsername('livestreamuser');
            $user->setPassword('$2y$13$test');
            $user->setRoles(['ROLE_USER']);
            
            $this->em->persist($user);
            $this->em->flush();
        }

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        $this->em = null;
    }
}
