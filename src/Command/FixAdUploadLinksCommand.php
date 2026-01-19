<?php

namespace App\Command;

use App\Repository\AdRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-ad-upload-links',
    description: 'Исправляет ссылки /upload на /videos/upload в рекламных объявлениях'
)]
class FixAdUploadLinksCommand extends Command
{
    public function __construct(
        private AdRepository $adRepository,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Находим все объявления с неправильными ссылками
        $ads = $this->adRepository->createQueryBuilder('a')
            ->where('a.clickUrl LIKE :upload')
            ->setParameter('upload', '%/upload%')
            ->getQuery()
            ->getResult();

        if (empty($ads)) {
            $io->success('Все ссылки уже исправлены!');
            return Command::SUCCESS;
        }

        $io->section('Найдены объявления с неправильными ссылками:');
        
        $fixed = 0;
        foreach ($ads as $ad) {
            $oldUrl = $ad->getClickUrl();
            
            // Исправляем ссылку
            if ($oldUrl === '/upload') {
                $newUrl = '/videos/upload';
                $ad->setClickUrl($newUrl);
                $ad->setUpdatedAt(new \DateTimeImmutable());
                
                $io->text("✓ ID {$ad->getId()}: '{$ad->getName()}' - {$oldUrl} → {$newUrl}");
                $fixed++;
            }
        }

        if ($fixed > 0) {
            $this->em->flush();
            $io->success("Исправлено {$fixed} объявлений!");
        } else {
            $io->note('Нет объявлений для исправления');
        }

        return Command::SUCCESS;
    }
}