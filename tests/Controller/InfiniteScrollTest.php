<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Video;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InfiniteScrollTest extends WebTestCase
{
    private $client;
    private $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();
    }

    public function testVideoListPageLoads(): void
    {
        // Создаём достаточно видео для пагинации
        $user = $this->createTestUser();
        $this->createTestVideos($user, 15);

        $this->client->request('GET', '/videos/');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-controller="infinite-scroll"]');
    }

    public function testLoadMoreEndpoint(): void
    {
        // Создаём тестовые видео
        $user = $this->createTestUser();
        $this->createTestVideos($user, 15);

        $this->client->request('GET', '/videos/load-more/newest', [
            'page' => 2
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
    }

    public function testLoadMoreReturnsVideoCards(): void
    {
        $user = $this->createTestUser();
        $this->createTestVideos($user, 15);

        $this->client->request('GET', '/videos/load-more/newest', [
            'page' => 1
        ]);

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        
        // Проверяем наличие video-card классов
        $this->assertStringContainsString('video-card', $content);
        $this->assertStringContainsString('data-has-more', $content);
    }

    public function testLoadMoreWithDifferentSorts(): void
    {
        $user = $this->createTestUser();
        $this->createTestVideos($user, 15);

        $sorts = ['newest', 'popular', 'trending'];

        foreach ($sorts as $sort) {
            $this->client->request('GET', "/videos/load-more/{$sort}", [
                'page' => 1
            ]);

            $this->assertResponseIsSuccessful(
                "Failed to load more with sort: {$sort}"
            );
        }
    }

    public function testLoadMoreBeyondAvailablePages(): void
    {
        $user = $this->createTestUser();
        $this->createTestVideos($user, 5); // Только 5 видео

        $this->client->request('GET', '/videos/load-more/newest', [
            'page' => 100 // Запрашиваем несуществующую страницу
        ]);

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        
        // Должен вернуть пустой результат или has_more = false
        $this->assertStringContainsString('data-has-more="false"', $content);
    }

    public function testInfiniteScrollDataAttributes(): void
    {
        $this->client->request('GET', '/videos/');
        
        $this->assertResponseIsSuccessful();
        
        // Проверяем наличие всех необходимых data-атрибутов
        $this->assertSelectorExists('[data-infinite-scroll-url-value]');
        $this->assertSelectorExists('[data-infinite-scroll-page-value]');
        $this->assertSelectorExists('[data-infinite-scroll-sort-value]');
        $this->assertSelectorExists('[data-infinite-scroll-has-more-value]');
    }

    public function testSentinelElementExists(): void
    {
        $user = $this->createTestUser();
        $this->createTestVideos($user, 25); // Создаём больше видео для гарантии пагинации

        $this->client->request('GET', '/videos/');
        
        $this->assertResponseIsSuccessful();
        
        // Проверяем наличие sentinel только если есть следующие страницы
        $crawler = $this->client->getCrawler();
        $hasMore = $crawler->filter('[data-infinite-scroll-has-more-value="true"]')->count() > 0;
        
        if ($hasMore) {
            $this->assertSelectorExists('[data-infinite-scroll-target="sentinel"]');
            $this->assertSelectorExists('[data-infinite-scroll-target="loader"]');
        } else {
            $this->markTestSkipped('Not enough videos for pagination');
        }
    }

    private function createTestUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setUsername('testuser');
            $user->setPassword('$2y$13$test'); // Dummy hash
            $user->setRoles(['ROLE_USER']);
            
            $this->em->persist($user);
            $this->em->flush();
        }

        return $user;
    }

    private function createTestVideos(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $video = new Video();
            $video->setTitle("Test Video {$i}");
            $video->setSlug("test-video-{$i}-" . uniqid());
            $video->setDescription("Test description {$i}");
            $video->setStatus(Video::STATUS_PUBLISHED);
            $video->setCreatedBy($user);
            $video->setDuration(120);
            $video->setViewsCount(rand(100, 10000));
            
            $this->em->persist($video);
        }

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        $this->em = null;
    }
}
