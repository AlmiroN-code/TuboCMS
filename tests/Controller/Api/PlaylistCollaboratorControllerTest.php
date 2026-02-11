<?php

namespace App\Tests\Controller\Api;

use App\Entity\Channel;
use App\Entity\ChannelPlaylist;
use App\Entity\PlaylistCollaborator;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PlaylistCollaboratorControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private User $collaborator;
    private Channel $channel;
    private ChannelPlaylist $playlist;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Создаём тестовых пользователей
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        
        $this->owner = new User();
        $this->owner->setUsername('owner_' . uniqid());
        $this->owner->setEmail('owner_' . uniqid() . '@test.com');
        $this->owner->setPassword($hasher->hashPassword($this->owner, 'password'));
        $this->owner->setRoles(['ROLE_USER']);
        $this->owner->setVerified(true);
        
        $this->collaborator = new User();
        $this->collaborator->setUsername('collab_' . uniqid());
        $this->collaborator->setEmail('collab_' . uniqid() . '@test.com');
        $this->collaborator->setPassword($hasher->hashPassword($this->collaborator, 'password'));
        $this->collaborator->setRoles(['ROLE_USER']);
        $this->collaborator->setVerified(true);

        $this->em->persist($this->owner);
        $this->em->persist($this->collaborator);
        $this->em->flush();

        // Создаём канал
        $this->channel = new Channel();
        $this->channel->setOwner($this->owner);
        $this->channel->setName('Test Channel');
        $this->channel->setSlug('test-channel-' . uniqid());
        $this->em->persist($this->channel);
        $this->em->flush();

        // Создаём плейлист
        $this->playlist = new ChannelPlaylist();
        $this->playlist->setChannel($this->channel);
        $this->playlist->setTitle('Test Playlist');
        $this->playlist->setSlug('test-playlist-' . uniqid());
        $this->em->persist($this->playlist);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testToggleCollaborative(): void
    {
        $this->client->loginUser($this->owner);

        // Включаем collaborative режим
        $this->client->request('POST', '/api/playlist/' . $this->playlist->getId() . '/collaborators/toggle-collaborative');
        
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['isCollaborative']);

        // Выключаем collaborative режим
        $this->client->request('POST', '/api/playlist/' . $this->playlist->getId() . '/collaborators/toggle-collaborative');
        
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertFalse($data['isCollaborative']);
    }

    public function testAddCollaborator(): void
    {
        $this->client->loginUser($this->owner);

        // Включаем collaborative режим
        $this->playlist->setIsCollaborative(true);
        $this->em->flush();

        // Добавляем соавтора
        $this->client->request('POST', '/api/playlist/' . $this->playlist->getId() . '/collaborators', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => $this->collaborator->getUsername(),
                'permission' => PlaylistCollaborator::PERMISSION_ADD
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testListCollaborators(): void
    {
        $this->client->loginUser($this->owner);

        // Включаем collaborative режим и добавляем соавтора
        $this->playlist->setIsCollaborative(true);
        $this->em->flush();

        $collaboratorEntity = new PlaylistCollaborator();
        $collaboratorEntity->setPlaylist($this->playlist);
        $collaboratorEntity->setUser($this->collaborator);
        $collaboratorEntity->setPermission(PlaylistCollaborator::PERMISSION_ADD);
        $collaboratorEntity->setAddedBy($this->owner);
        $this->em->persist($collaboratorEntity);
        $this->em->flush();

        // Получаем список соавторов
        $this->client->request('GET', '/api/playlist/' . $this->playlist->getId() . '/collaborators');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($this->collaborator->getUsername(), $data[0]['username']);
    }

    public function testUpdateCollaboratorPermission(): void
    {
        $this->client->loginUser($this->owner);

        // Добавляем соавтора
        $this->playlist->setIsCollaborative(true);
        $collaboratorEntity = new PlaylistCollaborator();
        $collaboratorEntity->setPlaylist($this->playlist);
        $collaboratorEntity->setUser($this->collaborator);
        $collaboratorEntity->setPermission(PlaylistCollaborator::PERMISSION_ADD);
        $collaboratorEntity->setAddedBy($this->owner);
        $this->em->persist($collaboratorEntity);
        $this->em->flush();

        // Обновляем права
        $this->client->request('PUT', '/api/playlist/' . $this->playlist->getId() . '/collaborators/' . $this->collaborator->getId(), [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['permission' => PlaylistCollaborator::PERMISSION_MANAGE])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testRemoveCollaborator(): void
    {
        $this->client->loginUser($this->owner);

        // Добавляем соавтора
        $this->playlist->setIsCollaborative(true);
        $collaboratorEntity = new PlaylistCollaborator();
        $collaboratorEntity->setPlaylist($this->playlist);
        $collaboratorEntity->setUser($this->collaborator);
        $collaboratorEntity->setPermission(PlaylistCollaborator::PERMISSION_ADD);
        $collaboratorEntity->setAddedBy($this->owner);
        $this->em->persist($collaboratorEntity);
        $this->em->flush();

        // Удаляем соавтора
        $this->client->request('DELETE', '/api/playlist/' . $this->playlist->getId() . '/collaborators/' . $this->collaborator->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testCannotAccessWithoutAuth(): void
    {
        $this->client->request('GET', '/api/playlist/' . $this->playlist->getId() . '/collaborators');
        $this->assertResponseRedirects('/login');
    }

    public function testCannotManageOthersPlaylist(): void
    {
        $this->client->loginUser($this->collaborator);

        $this->client->request('POST', '/api/playlist/' . $this->playlist->getId() . '/collaborators/toggle-collaborative');
        $this->assertResponseStatusCodeSame(403);
    }
}
