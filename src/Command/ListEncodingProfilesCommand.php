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
    name: 'app:list-encoding-profiles',
    description: 'Показывает список профилей кодирования'
)]
class ListEncodingProfilesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profiles = $this->em->getRepository(VideoEncodingProfile::class)
            ->findBy([], ['orderPosition' => 'ASC']);

        if (empty($profiles)) {
            $io->warning('Профили кодирования не найдены');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($profiles as $profile) {
            $rows[] = [
                $profile->getId(),
                $profile->getName(),
                $profile->getResolution(),
                $profile->getBitrate() . ' kbps',
                $profile->getCodec(),
                strtoupper($profile->getFormat() ?? 'MP4'),
                $profile->isActive() ? 'Да' : 'Нет'
            ];
        }

        $io->table(
            ['ID', 'Название', 'Разрешение', 'Битрейт', 'Кодек', 'Формат', 'Активен'],
            $rows
        );

        $io->success('Найдено ' . count($profiles) . ' профилей кодирования');

        return Command::SUCCESS;
    }
}