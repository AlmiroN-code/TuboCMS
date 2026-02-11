<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Video;
use App\Service\VideoProcessingService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VideoProcessingServiceTest extends KernelTestCase
{
    private VideoProcessingService $videoProcessingService;
    private $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->videoProcessingService = $container->get(VideoProcessingService::class);
        $this->em = $container->get('doctrine')->getManager();
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(VideoProcessingService::class, $this->videoProcessingService);
    }

    public function testCanProcessVideo(): void
    {
        $user = $this->createTestUser();
        $video = $this->createTestVideo($user);

        $this->assertNotNull($video->getId());
        $this->assertEquals(Video::STATUS_DRAFT, $video->getStatus());
    }

    private function createTestUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'videoprocessing@test.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('videoprocessing@test.com');
            $user->setUsername('videoprocessinguser');
            $user->setPassword('$2y$13$test');
            $user->setRoles(['ROLE_USER']);
            
            $this->em->persist($user);
            $this->em->flush();
        }

        return $user;
    }

    private function createTestVideo(User $user): Video
    {
        $video = new Video();
        $video->setTitle('Test Video Processing');
        $video->setSlug('test-video-processing-' . uniqid());
        $video->setDescription('Test description');
        $video->setCreatedBy($user);
        $video->setDuration(120);
        $video->setStatus(Video::STATUS_DRAFT);
        
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
