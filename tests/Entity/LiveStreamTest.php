<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\LiveStream;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class LiveStreamTest extends TestCase
{
    public function testLiveStreamCreation(): void
    {
        $stream = new LiveStream();
        
        $this->assertNull($stream->getId());
        $this->assertNotNull($stream->getStreamKey());
        $this->assertEquals(LiveStream::STATUS_SCHEDULED, $stream->getStatus());
        $this->assertEquals(0, $stream->getViewersCount());
        $this->assertEquals(0, $stream->getPeakViewersCount());
        $this->assertEquals(0, $stream->getTotalViews());
        $this->assertInstanceOf(\DateTimeImmutable::class, $stream->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $stream->getUpdatedAt());
    }

    public function testSettersAndGetters(): void
    {
        $stream = new LiveStream();
        $user = new User();
        
        $stream->setTitle('Test Stream');
        $stream->setDescription('Test Description');
        $stream->setSlug('test-stream');
        $stream->setStreamer($user);
        
        $this->assertEquals('Test Stream', $stream->getTitle());
        $this->assertEquals('Test Description', $stream->getDescription());
        $this->assertEquals('test-stream', $stream->getSlug());
        $this->assertSame($user, $stream->getStreamer());
    }

    public function testStatusTransitions(): void
    {
        $stream = new LiveStream();
        
        $this->assertEquals(LiveStream::STATUS_SCHEDULED, $stream->getStatus());
        $this->assertFalse($stream->isLive());
        
        $stream->setStatus(LiveStream::STATUS_LIVE);
        $this->assertEquals(LiveStream::STATUS_LIVE, $stream->getStatus());
        $this->assertTrue($stream->isLive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $stream->getStartedAt());
        
        $stream->setStatus(LiveStream::STATUS_ENDED);
        $this->assertEquals(LiveStream::STATUS_ENDED, $stream->getStatus());
        $this->assertFalse($stream->isLive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $stream->getEndedAt());
    }

    public function testViewersCount(): void
    {
        $stream = new LiveStream();
        
        $stream->incrementViewersCount();
        $this->assertEquals(1, $stream->getViewersCount());
        $this->assertEquals(1, $stream->getPeakViewersCount());
        
        $stream->incrementViewersCount();
        $this->assertEquals(2, $stream->getViewersCount());
        $this->assertEquals(2, $stream->getPeakViewersCount());
        
        $stream->decrementViewersCount();
        $this->assertEquals(1, $stream->getViewersCount());
        $this->assertEquals(2, $stream->getPeakViewersCount());
    }

    public function testTotalViews(): void
    {
        $stream = new LiveStream();
        
        $this->assertEquals(0, $stream->getTotalViews());
        
        $stream->incrementTotalViews();
        $this->assertEquals(1, $stream->getTotalViews());
        
        $stream->incrementTotalViews();
        $this->assertEquals(2, $stream->getTotalViews());
    }

    public function testRegenerateStreamKey(): void
    {
        $stream = new LiveStream();
        $originalKey = $stream->getStreamKey();
        
        $stream->regenerateStreamKey();
        $newKey = $stream->getStreamKey();
        
        $this->assertNotEquals($originalKey, $newKey);
        $this->assertEquals(64, strlen($newKey));
    }

    public function testDuration(): void
    {
        $stream = new LiveStream();
        
        $this->assertNull($stream->getDuration());
        
        $stream->setStatus(LiveStream::STATUS_LIVE);
        sleep(1);
        
        $duration = $stream->getDuration();
        $this->assertGreaterThanOrEqual(1, $duration);
        
        $stream->setStatus(LiveStream::STATUS_ENDED);
        $finalDuration = $stream->getDuration();
        $this->assertGreaterThanOrEqual($duration, $finalDuration);
    }
}
