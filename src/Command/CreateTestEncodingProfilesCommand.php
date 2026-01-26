<?php

namespace App\Command;

use App\Entity\VideoEncodingProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-test-encoding-profiles',
    description: 'Создает тестовые профили кодирования'
)]
class CreateTestEncodingProfilesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Проверяем, есть ли уже профили
        $existingCount = $this->em->getRepository(VideoEncodingProfile::class)->count([]);
        if ($existingCount > 0) {
            $io->note("Найдено $existingCount существующих профилей. Добавляем только новые.");
        }

        $profiles = [
            [
                'name' => '4K Ultra HD',
                'resolution' => '3840x2160',
                'bitrate' => 25000,
                'codec' => 'libx264',
                'format' => 'mp4',
                'order_position' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Full HD 1080p',
                'resolution' => '1920x1080',
                'bitrate' => 8000,
                'codec' => 'libx264',
                'format' => 'mp4',
                'order_position' => 2,
                'is_active' => true
            ],
            [
                'name' => 'HD 720p',
                'resolution' => '1280x720',
                'bitrate' => 5000,
                'codec' => 'libx264',
                'format' => 'mp4',
                'order_position' => 3,
                'is_active' => true
            ],
            [
                'name' => 'SD 480p',
                'resolution' => '854x480',
                'bitrate' => 2500,
                'codec' => 'libx264',
                'format' => 'mp4',
                'order_position' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Mobile 360p',
                'resolution' => '640x360',
                'bitrate' => 1000,
                'codec' => 'libx264',
                'format' => 'mp4',
                'order_position' => 5,
                'is_active' => true
            ],
            [
                'name' => 'Apple ProRes HD',
                'resolution' => '1920x1080',
                'bitrate' => 15000,
                'codec' => 'prores',
                'format' => 'mov',
                'order_position' => 6,
                'is_active' => false
            ],
            [
                'name' => 'AVI Compatibility',
                'resolution' => '1280x720',
                'bitrate' => 6000,
                'codec' => 'libx264',
                'format' => 'avi',
                'order_position' => 7,
                'is_active' => false
            ],
            [
                'name' => 'MKV High Quality',
                'resolution' => '1920x1080',
                'bitrate' => 12000,
                'codec' => 'libx265',
                'format' => 'mkv',
                'order_position' => 8,
                'is_active' => false
            ]
        ];

        $created = 0;
        foreach ($profiles as $profileData) {
            // Проверяем, существует ли уже профиль с таким именем
            $existing = $this->em->getRepository(VideoEncodingProfile::class)
                ->findOneBy(['name' => $profileData['name']]);
            
            if ($existing) {
                $io->text("Профиль '{$profileData['name']}' уже существует, пропускаем");
                continue;
            }

            $profile = new VideoEncodingProfile();
            $profile->setName($profileData['name']);
            $profile->setResolution($profileData['resolution']);
            $profile->setBitrate($profileData['bitrate']);
            $profile->setCodec($profileData['codec']);
            $profile->setFormat($profileData['format']);
            $profile->setOrderPosition($profileData['order_position']);
            $profile->setActive($profileData['is_active']);

            $this->em->persist($profile);
            $created++;
        }

        $this->em->flush();

        $io->success("Создано $created новых профилей кодирования");

        return Command::SUCCESS;
    }
}