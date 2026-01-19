<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\Video;
use App\Entity\VideoEncodingProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create encoding profiles
        $profiles = [
            ['name' => '480p', 'resolution' => '480p', 'bitrate' => 1000, 'order' => 1],
            ['name' => '720p', 'resolution' => '720p', 'bitrate' => 2500, 'order' => 2],
            ['name' => '1080p', 'resolution' => '1080p', 'bitrate' => 5000, 'order' => 3],
        ];

        foreach ($profiles as $profileData) {
            $profile = new VideoEncodingProfile();
            $profile->setName($profileData['name']);
            $profile->setResolution($profileData['resolution']);
            $profile->setBitrate($profileData['bitrate']);
            $profile->setCodec('h264');
            $profile->setActive(true);
            $profile->setOrderPosition($profileData['order']);
            $manager->persist($profile);
        }

        // Create categories
        $categories = [
            ['name' => 'Любительское', 'slug' => 'amateur', 'desc' => 'Любительские видео'],
            ['name' => 'Профессиональное', 'slug' => 'professional', 'desc' => 'Профессиональные видео'],
            ['name' => 'Блондинки', 'slug' => 'blonde', 'desc' => 'Видео с блондинками'],
            ['name' => 'Брюнетки', 'slug' => 'brunette', 'desc' => 'Видео с брюнетками'],
            ['name' => 'Азиатки', 'slug' => 'asian', 'desc' => 'Азиатские видео'],
            ['name' => 'Латинки', 'slug' => 'latina', 'desc' => 'Латиноамериканские видео'],
        ];

        $categoryObjects = [];
        foreach ($categories as $index => $catData) {
            $category = new Category();
            $category->setName($catData['name']);
            $category->setSlug($catData['slug']);
            $category->setDescription($catData['desc']);
            $category->setVideosCount(0);
            $category->setActive(true);
            $category->setOrderPosition($index + 1);
            $manager->persist($category);
            $categoryObjects[] = $category;
        }

        // Create tags
        $tags = ['HD', '4K', 'Новинки', 'Популярное', 'Эксклюзив', 'Топ', 'Горячее'];
        $tagObjects = [];
        foreach ($tags as $tagName) {
            $tag = new Tag();
            $tag->setName($tagName);
            $tag->setSlug(strtolower(str_replace(' ', '-', $tagName)));
            $tag->setUsageCount(0);
            $manager->persist($tag);
            $tagObjects[] = $tag;
        }

        // Create test user
        $user = new User();
        $user->setEmail('admin@rextube.test');
        $user->setUsername('admin');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'admin123'));
        $user->setVerified(true);
        $user->setPremium(true);
        $manager->persist($user);

        // Create regular user
        $regularUser = new User();
        $regularUser->setEmail('user@rextube.test');
        $regularUser->setUsername('user');
        $regularUser->setPassword($this->passwordHasher->hashPassword($regularUser, 'user123'));
        $manager->persist($regularUser);

        // Create sample videos
        for ($i = 1; $i <= 20; $i++) {
            $video = new Video();
            $video->setTitle("Тестовое видео #{$i}");
            $video->setDescription("Описание тестового видео номер {$i}. Это демонстрационный контент.");
            $video->setSlug("test-video-{$i}");
            $video->setDuration(rand(300, 3600)); // 5-60 минут
            $video->setResolution('1920x1080');
            $video->setFormat('mp4');
            $video->setStatus(Video::STATUS_PUBLISHED);
            $video->setFeatured($i <= 5); // Первые 5 видео - избранные
            $video->setViewsCount(rand(100, 10000));
            $video->setCreatedBy($i % 2 === 0 ? $user : $regularUser);
            
            // Assign random category
            $video->setCategory($categoryObjects[array_rand($categoryObjects)]);
            
            // Assign random tags
            $numTags = rand(1, 3);
            $randomTags = array_rand($tagObjects, $numTags);
            if (!is_array($randomTags)) {
                $randomTags = [$randomTags];
            }
            foreach ($randomTags as $tagIndex) {
                $video->addTag($tagObjects[$tagIndex]);
            }
            
            $manager->persist($video);
        }

        $manager->flush();
    }
}
