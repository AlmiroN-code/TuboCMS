<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Entity\Video;
use App\Message\ProcessVideoEncodingMessage;
use Symfony\Component\Dotenv\Dotenv;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Messenger\MessageBusInterface;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel($_ENV['APP_ENV'] ?? 'dev', $_ENV['APP_DEBUG'] ?? false);
$kernel->boot();

$container = $kernel->getContainer();

// Get services using ManagerRegistry
$doctrine = $container->get('doctrine');
$em = $doctrine->getManager();
$messageBus = $container->get('messenger.bus.default');

// Create a test video
$video = new Video();
$video->setTitle('Test Video for Poster Extraction');
$video->setDescription('This is a test video to check poster extraction');
$video->setSlug('test-video-' . time());
$video->setStatus(Video::STATUS_PROCESSING);
$video->setProcessingStatus('pending');
$video->setTempVideoFile('videos/tmp/test_video.mp4');

// Get first user (admin)
$user = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
if (!$user) {
    echo "No users found in database\n";
    exit(1);
}

$video->setCreatedBy($user);

$em->persist($video);
$em->flush();

echo "Video created with ID: " . $video->getId() . "\n";
echo "Dispatching message to queue...\n";

$messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));

echo "Message dispatched successfully\n";
echo "Check /tmp/poster_debug.log for debug information\n";
