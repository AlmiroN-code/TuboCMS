<?php

namespace App\Tests\Service;

use App\Entity\ChatMessage;
use App\Entity\User;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ChatServiceTest extends KernelTestCase
{
    private ChatService $chatService;
    private User $testUser;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $this->chatService = $container->get(ChatService::class);
        
        $em = $container->get('doctrine')->getManager();
        
        // Проверяем, существует ли уже тестовый пользователь
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => 'chattest@example.com']);
        
        if ($existingUser) {
            $this->testUser = $existingUser;
        } else {
            // Создаем тестового пользователя
            $this->testUser = new User();
            $this->testUser->setUsername('chattest_' . uniqid());
            $this->testUser->setEmail('chattest@example.com');
            $this->testUser->setPassword('password');
            
            $em->persist($this->testUser);
            $em->flush();
        }
    }

    public function testSendMessage(): void
    {
        $message = $this->chatService->sendMessage('test-room', $this->testUser, 'Hello World');
        
        $this->assertInstanceOf(ChatMessage::class, $message);
        $this->assertEquals('test-room', $message->getRoomId());
        $this->assertEquals('Hello World', $message->getMessage());
        $this->assertEquals($this->testUser->getId(), $message->getUser()->getId());
        $this->assertEquals('text', $message->getType());
    }

    public function testGetMessages(): void
    {
        // Отправляем несколько сообщений
        $this->chatService->sendMessage('test-room-2', $this->testUser, 'Message 1');
        $this->chatService->sendMessage('test-room-2', $this->testUser, 'Message 2');
        $this->chatService->sendMessage('test-room-2', $this->testUser, 'Message 3');
        
        $messages = $this->chatService->getMessages('test-room-2', 10);
        
        $this->assertCount(3, $messages);
    }

    public function testFormatMessageForClient(): void
    {
        $message = $this->chatService->sendMessage('test-room-3', $this->testUser, 'Test message');
        $formatted = $this->chatService->formatMessageForClient($message);
        
        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('id', $formatted);
        $this->assertArrayHasKey('roomId', $formatted);
        $this->assertArrayHasKey('user', $formatted);
        $this->assertArrayHasKey('message', $formatted);
        $this->assertArrayHasKey('type', $formatted);
        $this->assertArrayHasKey('createdAt', $formatted);
        
        $this->assertEquals('test-room-3', $formatted['roomId']);
        $this->assertEquals('Test message', $formatted['message']);
        $this->assertEquals($this->testUser->getUsername(), $formatted['user']['username']);
    }

    public function testDeleteMessage(): void
    {
        $message = $this->chatService->sendMessage('test-room-4', $this->testUser, 'To be deleted');
        $messageId = $message->getId();
        
        $result = $this->chatService->deleteMessage($messageId, $this->testUser);
        
        $this->assertTrue($result);
        
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $deletedMessage = $em->getRepository(ChatMessage::class)->find($messageId);
        
        $this->assertTrue($deletedMessage->isDeleted());
        $this->assertNotNull($deletedMessage->getDeletedAt());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Очистка тестовых данных
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        
        $em->createQuery('DELETE FROM App\Entity\ChatMessage')->execute();
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.email = :email')
            ->setParameter('email', 'chattest@example.com')
            ->execute();
    }
}
