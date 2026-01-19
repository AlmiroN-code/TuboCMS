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
    name: 'app:video:init-profiles',
    description: 'Initialize default video encoding profiles',
)]
class InitializeEncodingProfilesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profiles = [
            [
                'name' => '360p',
                'resolution' => '640x360',
                'bitrate' => 800,
                'codec' => 'libx264',
                'order' => 1
            ],
            [
                'name' => '720p',
                'resolution' => '1280x720',
                'bitrate' => 2500,
                'codec' => 'libx264',
                'order' => 2
            ],
            [
                'name' => '1080p',
                'resolution' => '1920x1080',
                'bitrate' => 5000,
                'codec' => 'libx264',
                'order' => 3
            ]
        ];

        $repository = $this->em->getRepository(VideoEncodingProfile::class);
        $created = 0;

        foreach ($profiles as $profileData) {
            // Проверяем, существует ли уже профиль с таким именем
            $existing = $repository->findOneBy(['name' => $profileData['name']]);
            
            if (!$existing) {
                $profile = new VideoEncodingProfile();
                $profile->setName($profileData['name']);
                $profile->setResolution($profileData['resolution']);
                $profile->setBitrate($profileData['bitrate']);
                $profile->setCodec($profileData['codec']);
                $profile->setOrderPosition($profileData['order']);
                $profile->setActive(true);

                $this->em->persist($profile);
                $created++;

                $io->success(sprintf('Created profile: %s (%s, %d kbps)', 
                    $profileData['name'], 
                    $profileData['resolution'], 
                    $profileData['bitrate']
                ));
            } else {
                $io->info(sprintf('Profile already exists: %s', $profileData['name']));
            }
        }

        if ($created > 0) {
            $this->em->flush();
            $io->success(sprintf('Created %d new encoding profiles', $created));
        } else {
            $io->info('All default profiles already exist');
        }

        return Command::SUCCESS;
    }
}