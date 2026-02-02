<?php

namespace App\Command;

use App\Entity\Video;
use App\Message\ProcessVideoEncodingMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:test-poster',
    description: 'Test poster extraction by creating a test video and dispatching it to the queue'
)]
class TestPosterExtractionCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Creating test video...');

        // Create a test video
        $video = new Video();
        $video->setTitle('Test Video for Poster Extraction - ' . date('Y-m-d H:i:s'));
        $video->setDescription('This is a test video to check poster extraction');
        $video->setSlug('test-video-' . time());
        $video->setStatus(Video::STATUS_PROCESSING);
        $video->setProcessingStatus('pending');
        $video->setTempVideoFile('videos/tmp/test_video.mp4');

        // Get first user (admin)
        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy([]);
        if (!$user) {
            $output->writeln('<error>No users found in database</error>');
            return Command::FAILURE;
        }

        $video->setCreatedBy($user);

        $this->em->persist($video);
        $this->em->flush();

        $output->writeln('<info>Video created with ID: ' . $video->getId() . '</info>');
        $output->writeln('Dispatching message to queue...');

        $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));

        $output->writeln('<info>Message dispatched successfully</info>');
        $output->writeln('Check /tmp/poster_debug.log for debug information');

        return Command::SUCCESS;
    }
}
