<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Entity\Video;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TrendingAlgorithmTest extends KernelTestCase
{
    private $em;
    private VideoRepository $videoRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->videoRepository = $this->em->getRepository(Video::class);
    }

    public function testTrendingAlgorithmPrioritizesEngagement(): void
    {
        $user = $this->createTestUser();
        
        // Видео с высоким engagement (лайки + комментарии)
        $highEngagement = $this->createVideo($user, [
            'title' => 'High Engagement Video',
            'views' => 1000,
            'likes' => 100,
            'dislikes' => 5,
            'comments' => 50,
            'daysOld' => 3
        ]);
        
        // Видео с только просмотрами
        $onlyViews = $this->createVideo($user, [
            'title' => 'Only Views Video',
            'views' => 2000,
            'likes' => 10,
            'dislikes' => 2,
            'comments' => 5,
            'daysOld' => 5
        ]);

        $trending = $this->videoRepository->findTrending(10, 0);
        
        $this->assertNotEmpty($trending);
        
        // Видео с высоким engagement должно быть выше
        $highEngagementPosition = array_search($highEngagement->getId(), array_map(fn($v) => $v->getId(), $trending));
        $onlyViewsPosition = array_search($onlyViews->getId(), array_map(fn($v) => $v->getId(), $trending));
        
        if ($highEngagementPosition !== false && $onlyViewsPosition !== false) {
            $this->assertLessThan(
                $onlyViewsPosition,
                $highEngagementPosition,
                'Video with high engagement should rank higher'
            );
        }
    }

    public function testTrendingAlgorithmBoostsFreshContent(): void
    {
        $user = $this->createTestUser();
        
        // Свежее видео (2 дня)
        $freshVideo = $this->createVideo($user, [
            'title' => 'Fresh Video',
            'views' => 500,
            'likes' => 20,
            'dislikes' => 1,
            'comments' => 10,
            'daysOld' => 2
        ]);
        
        // Старое видео (8 дней)
        $oldVideo = $this->createVideo($user, [
            'title' => 'Old Video',
            'views' => 600,
            'likes' => 20,
            'dislikes' => 1,
            'comments' => 10,
            'daysOld' => 8
        ]);

        $trending = $this->videoRepository->findTrending(10, 0);
        
        $this->assertNotEmpty($trending);
        
        // Свежее видео должно получить буст
        $freshPosition = array_search($freshVideo->getId(), array_map(fn($v) => $v->getId(), $trending));
        $oldPosition = array_search($oldVideo->getId(), array_map(fn($v) => $v->getId(), $trending));
        
        if ($freshPosition !== false && $oldPosition !== false) {
            $this->assertLessThan(
                $oldPosition,
                $freshPosition,
                'Fresh video should rank higher due to freshness boost'
            );
        }
    }

    public function testTrendingAlgorithmPenalizesDislik(): void
    {
        $user = $this->createTestUser();
        
        // Видео с высоким рейтингом
        $highRated = $this->createVideo($user, [
            'title' => 'High Rated Video',
            'views' => 1000,
            'likes' => 100,
            'dislikes' => 5,
            'comments' => 20,
            'daysOld' => 3
        ]);
        
        // Видео с низким рейтингом (много дизлайков)
        $lowRated = $this->createVideo($user, [
            'title' => 'Low Rated Video',
            'views' => 1000,
            'likes' => 50,
            'dislikes' => 80,
            'comments' => 20,
            'daysOld' => 3
        ]);

        $trending = $this->videoRepository->findTrending(10, 0);
        
        $this->assertNotEmpty($trending);
        
        $highRatedPosition = array_search($highRated->getId(), array_map(fn($v) => $v->getId(), $trending));
        $lowRatedPosition = array_search($lowRated->getId(), array_map(fn($v) => $v->getId(), $trending));
        
        if ($highRatedPosition !== false && $lowRatedPosition !== false) {
            $this->assertLessThan(
                $lowRatedPosition,
                $highRatedPosition,
                'Video with better like/dislike ratio should rank higher'
            );
        }
    }

    public function testTrendingOnlyIncludesRecentVideos(): void
    {
        $user = $this->createTestUser();
        
        // Видео старше 7 дней не должно попасть в тренды
        $veryOldVideo = $this->createVideo($user, [
            'title' => 'Very Old Video',
            'views' => 10000,
            'likes' => 500,
            'dislikes' => 10,
            'comments' => 100,
            'daysOld' => 10
        ]);

        $trending = $this->videoRepository->findTrending(100, 0);
        
        $veryOldInTrending = in_array(
            $veryOldVideo->getId(),
            array_map(fn($v) => $v->getId(), $trending)
        );
        
        $this->assertFalse(
            $veryOldInTrending,
            'Videos older than 7 days should not appear in trending'
        );
    }

    public function testTrendingReturnsPublishedVideosOnly(): void
    {
        $user = $this->createTestUser();
        
        $draftVideo = $this->createVideo($user, [
            'title' => 'Draft Video',
            'views' => 1000,
            'likes' => 100,
            'status' => Video::STATUS_DRAFT,
            'daysOld' => 2
        ]);

        $trending = $this->videoRepository->findTrending(100, 0);
        
        foreach ($trending as $video) {
            $this->assertEquals(
                Video::STATUS_PUBLISHED,
                $video->getStatus(),
                'Only published videos should appear in trending'
            );
        }
    }

    public function testTrendingPaginationWorks(): void
    {
        $user = $this->createTestUser();
        
        // Создаём 30 видео
        for ($i = 1; $i <= 30; $i++) {
            $this->createVideo($user, [
                'title' => "Video {$i}",
                'views' => 1000 - ($i * 10),
                'likes' => 50,
                'daysOld' => 2
            ]);
        }

        $page1 = $this->videoRepository->findTrending(10, 0);
        $page2 = $this->videoRepository->findTrending(10, 10);
        $page3 = $this->videoRepository->findTrending(10, 20);
        
        $this->assertCount(10, $page1);
        $this->assertCount(10, $page2);
        $this->assertCount(10, $page3);
        
        // Проверяем что страницы не пересекаются
        $page1Ids = array_map(fn($v) => $v->getId(), $page1);
        $page2Ids = array_map(fn($v) => $v->getId(), $page2);
        
        $this->assertEmpty(
            array_intersect($page1Ids, $page2Ids),
            'Pages should not contain duplicate videos'
        );
    }

    private function createTestUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'trending@test.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('trending@test.com');
            $user->setUsername('trendinguser');
            $user->setPassword('$2y$13$test');
            $user->setRoles(['ROLE_USER']);
            
            $this->em->persist($user);
            $this->em->flush();
        }

        return $user;
    }

    private function createVideo(User $user, array $data): Video
    {
        $video = new Video();
        $video->setTitle($data['title']);
        $video->setSlug(strtolower(str_replace(' ', '-', $data['title'])) . '-' . uniqid());
        $video->setDescription('Test description');
        $video->setCreatedBy($user);
        $video->setDuration(120);
        
        // Устанавливаем дату создания через рефлексию
        $createdAt = new \DateTimeImmutable("-{$data['daysOld']} days");
        $reflection = new \ReflectionClass($video);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($video, $createdAt);
        
        // Устанавливаем статус (это автоматически установит publishedAt для опубликованных видео)
        $video->setStatus($data['status'] ?? Video::STATUS_PUBLISHED);
        
        // Если видео опубликовано, устанавливаем publishedAt = createdAt для тестов
        if ($video->getStatus() === Video::STATUS_PUBLISHED) {
            $publishedAtProperty = $reflection->getProperty('publishedAt');
            $publishedAtProperty->setAccessible(true);
            $publishedAtProperty->setValue($video, $createdAt);
        }
        
        // Устанавливаем метрики
        $video->setViewsCount($data['views'] ?? 0);
        $video->setLikesCount($data['likes'] ?? 0);
        $video->setDislikesCount($data['dislikes'] ?? 0);
        $video->setCommentsCount($data['comments'] ?? 0);
        
        $this->em->persist($video);
        $this->em->flush();
        
        return $video;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        $this->em = null;
    }
}
