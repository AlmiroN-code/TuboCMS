<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\VideoChapter;
use PHPUnit\Framework\TestCase;

class VideoChapterTest extends TestCase
{
    public function testVideoChapterCreation(): void
    {
        $chapter = new VideoChapter();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $chapter->getCreatedAt());
        $this->assertNull($chapter->getId());
        $this->assertNull($chapter->getVideo());
        $this->assertEquals(0, $chapter->getTimestamp());
        $this->assertEquals('', $chapter->getTitle());
        $this->assertNull($chapter->getDescription());
    }

    public function testSettersAndGetters(): void
    {
        $chapter = new VideoChapter();
        $video = new Video();
        $user = new User();

        $chapter->setVideo($video);
        $chapter->setTimestamp(125);
        $chapter->setTitle('Введение');
        $chapter->setDescription('Описание главы');
        $chapter->setCreatedBy($user);

        $this->assertSame($video, $chapter->getVideo());
        $this->assertEquals(125, $chapter->getTimestamp());
        $this->assertEquals('Введение', $chapter->getTitle());
        $this->assertEquals('Описание главы', $chapter->getDescription());
        $this->assertSame($user, $chapter->getCreatedBy());
    }

    /**
     * @dataProvider timestampFormattingProvider
     */
    public function testFormattedTimestamp(int $seconds, string $expected): void
    {
        $chapter = new VideoChapter();
        $chapter->setTimestamp($seconds);

        $this->assertEquals($expected, $chapter->getFormattedTimestamp());
    }

    public static function timestampFormattingProvider(): array
    {
        return [
            'zero seconds' => [0, '00:00'],
            'under minute' => [45, '00:45'],
            'one minute' => [60, '01:00'],
            'minutes and seconds' => [125, '02:05'],
            'one hour' => [3600, '01:00:00'],
            'hours, minutes, seconds' => [3665, '01:01:05'],
            'multiple hours' => [7325, '02:02:05'],
        ];
    }
}
