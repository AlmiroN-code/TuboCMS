<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\VideoChapter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VideoChapterControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private KernelBrowser $client;
    private User $user;
    private Video $video;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();

        // Создаём тестового пользователя
        $this->user = new User();
        $this->user->setUsername('testuser_' . uniqid());
        $this->user->setEmail('test_' . uniqid() . '@example.com');
        $this->user->setPassword('password');
        $this->user->setRoles(['ROLE_USER']);
        $this->em->persist($this->user);

        // Создаём тестовое видео
        $this->video = new Video();
        $this->video->setTitle('Test Video');
        $this->video->setSlug('test-video-' . uniqid());
        $this->video->setCreatedBy($this->user);
        $this->video->setStatus(Video::STATUS_PUBLISHED);
        $this->em->persist($this->video);

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        // Очистка тестовых данных
        if ($this->video) {
            $chapters = $this->em->getRepository(VideoChapter::class)
                ->findBy(['video' => $this->video]);
            foreach ($chapters as $chapter) {
                $this->em->remove($chapter);
            }
            $this->em->remove($this->video);
        }
        if ($this->user) {
            $this->em->remove($this->user);
        }
        $this->em->flush();
        
        parent::tearDown();
    }

    public function testListChaptersEmpty(): void
    {
        $this->client->request('GET', '/api/video/' . $this->video->getId() . '/chapters');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data['chapters']);
        $this->assertCount(0, $data['chapters']);
    }

    public function testListChaptersWithData(): void
    {
        // Создаём главы
        $chapter1 = new VideoChapter();
        $chapter1->setVideo($this->video);
        $chapter1->setTimestamp(0);
        $chapter1->setTitle('Intro');
        $chapter1->setCreatedBy($this->user);
        $this->em->persist($chapter1);

        $chapter2 = new VideoChapter();
        $chapter2->setVideo($this->video);
        $chapter2->setTimestamp(120);
        $chapter2->setTitle('Main Part');
        $chapter2->setCreatedBy($this->user);
        $this->em->persist($chapter2);

        $this->em->flush();

        $this->client->request('GET', '/api/video/' . $this->video->getId() . '/chapters');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data['chapters']);
        $this->assertEquals('Intro', $data['chapters'][0]['title']);
        $this->assertEquals(0, $data['chapters'][0]['timestamp']);
        $this->assertEquals('Main Part', $data['chapters'][1]['title']);
        $this->assertEquals(120, $data['chapters'][1]['timestamp']);
    }

    public function testCreateChapterUnauthorized(): void
    {
        $this->client->request('POST', '/api/video/' . $this->video->getId() . '/chapters', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'timestamp' => 60,
            'title' => 'Test Chapter',
        ]));

        // Symfony редиректит на /login для неавторизованных запросов
        $this->assertResponseRedirects('/login');
    }

    public function testVideoNotFound(): void
    {
        $this->client->request('GET', '/api/video/999999/chapters');

        $this->assertResponseStatusCodeSame(404);
    }
}
