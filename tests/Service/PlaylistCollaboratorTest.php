<?php

namespace App\Tests\Service;

use App\Entity\Channel;
use App\Entity\ChannelPlaylist;
use App\Entity\PlaylistCollaborator;
use App\Entity\User;
use App\Service\PlaylistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PlaylistCollaboratorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PlaylistService $playlistService;
    private User $owner;
    private User $collaborator;
    private Channel $channel;
    private ChannelPlaylist $playlist;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->playlistService = static::getContainer()->get(PlaylistService::class);

        // Создаём владельца
        $this->owner = new User();
        $this->owner->setUsername('owner_' . uniqid());
        $this->owner->setEmail('owner_' . uniqid() . '@test.com');
        $this->owner->setPassword('password');
        $this->em->persist($this->owner);

        // Создаём соавтора
        $this->collaborator = new User();
        $this->collaborator->setUsername('collab_' . uniqid());
        $this->collaborator->setEmail('collab_' . uniqid() . '@test.com');
        $this->collaborator->setPassword('password');
        $this->em->persist($this->collaborator);

        // Создаём канал
        $this->channel = new Channel();
        $this->channel->setName('Test Channel');
        $this->channel->setSlug('test-channel-' . uniqid());
        $this->channel->setOwner($this->owner);
        $this->em->persist($this->channel);
        
        $this->em->flush();

        // Создаём плейлист
        $this->playlist = $this->playlistService->createPlaylist(
            $this->channel,
            'Test Playlist',
            'Test Description'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testMakePlaylistCollaborative(): void
    {
        $this->assertFalse($this->playlist->isCollaborative());

        $this->playlistService->makeCollaborative($this->playlist);

        $this->assertTrue($this->playlist->isCollaborative());
    }

    public function testAddCollaborator(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);

        $collaborator = $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_ADD,
            $this->owner
        );

        $this->assertNotNull($collaborator->getId());
        $this->assertEquals($this->collaborator, $collaborator->getUser());
        $this->assertEquals(PlaylistCollaborator::PERMISSION_ADD, $collaborator->getPermission());
        $this->assertEquals($this->owner, $collaborator->getAddedBy());
    }

    public function testCannotAddCollaboratorToNonCollaborativePlaylist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Плейлист не является совместным');

        $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator
        );
    }

    public function testCannotAddSameCollaboratorTwice(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $this->playlistService->addCollaborator($this->playlist, $this->collaborator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Пользователь уже является соавтором');

        $this->playlistService->addCollaborator($this->playlist, $this->collaborator);
    }

    public function testCannotAddOwnerAsCollaborator(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Владелец канала не может быть соавтором');

        $this->playlistService->addCollaborator($this->playlist, $this->owner);
    }

    public function testRemoveCollaborator(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $this->playlistService->addCollaborator($this->playlist, $this->collaborator);

        $collaborators = $this->playlistService->getCollaborators($this->playlist);
        $this->assertCount(1, $collaborators);

        $this->playlistService->removeCollaborator($this->playlist, $this->collaborator);

        $collaborators = $this->playlistService->getCollaborators($this->playlist);
        $this->assertCount(0, $collaborators);
    }

    public function testUpdateCollaboratorPermission(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_ADD
        );

        $this->playlistService->updateCollaboratorPermission(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_MANAGE
        );

        $this->em->clear();
        $playlist = $this->em->getRepository(ChannelPlaylist::class)->find($this->playlist->getId());
        $collaborator = $this->em->getRepository(User::class)->find($this->collaborator->getId());
        $permission = $playlist->getUserPermission($collaborator);
        $this->assertEquals(PlaylistCollaborator::PERMISSION_MANAGE, $permission);
    }

    public function testDisableCollaborativeRemovesAllCollaborators(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $this->playlistService->addCollaborator($this->playlist, $this->collaborator);

        $collaborators = $this->playlistService->getCollaborators($this->playlist);
        $this->assertCount(1, $collaborators);

        $this->playlistService->disableCollaborative($this->playlist);

        $this->assertFalse($this->playlist->isCollaborative());
        $collaborators = $this->playlistService->getCollaborators($this->playlist);
        $this->assertCount(0, $collaborators);
    }

    public function testCollaboratorCanAddPermission(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $collaboratorEntity = $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_ADD
        );

        $this->assertTrue($collaboratorEntity->canView());
        $this->assertTrue($collaboratorEntity->canAdd());
        $this->assertFalse($collaboratorEntity->canEdit());
        $this->assertFalse($collaboratorEntity->canManage());
    }

    public function testCollaboratorEditPermission(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $collaboratorEntity = $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_EDIT
        );

        $this->assertTrue($collaboratorEntity->canView());
        $this->assertTrue($collaboratorEntity->canAdd());
        $this->assertTrue($collaboratorEntity->canEdit());
        $this->assertFalse($collaboratorEntity->canManage());
    }

    public function testCollaboratorManagePermission(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $collaboratorEntity = $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_MANAGE
        );

        $this->assertTrue($collaboratorEntity->canView());
        $this->assertTrue($collaboratorEntity->canAdd());
        $this->assertTrue($collaboratorEntity->canEdit());
        $this->assertTrue($collaboratorEntity->canManage());
    }

    public function testCanUserAddToCollaborativePlaylist(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_ADD
        );

        $this->em->clear();
        $playlist = $this->em->find(ChannelPlaylist::class, $this->playlist->getId());
        $collaborator = $this->em->find(User::class, $this->collaborator->getId());

        $canAdd = $this->playlistService->canUserAddToPlaylist($playlist, $collaborator);
        $this->assertTrue($canAdd);
    }

    public function testOwnerCanAlwaysManagePlaylist(): void
    {
        $canManage = $this->playlistService->canUserManagePlaylist($this->playlist, $this->owner);
        $this->assertTrue($canManage);
    }

    public function testCollaboratorWithManagePermissionCanManage(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $this->playlistService->addCollaborator(
            $this->playlist,
            $this->collaborator,
            PlaylistCollaborator::PERMISSION_MANAGE
        );

        $this->em->clear();
        $playlist = $this->em->find(ChannelPlaylist::class, $this->playlist->getId());
        $collaborator = $this->em->find(User::class, $this->collaborator->getId());

        $canManage = $this->playlistService->canUserManagePlaylist($playlist, $collaborator);
        $this->assertTrue($canManage);
    }

    public function testGetCollaborativePlaylists(): void
    {
        $this->playlistService->makeCollaborative($this->playlist);
        $this->playlistService->addCollaborator($this->playlist, $this->collaborator);

        $playlists = $this->playlistService->getCollaborativePlaylists($this->collaborator);

        $this->assertCount(1, $playlists);
        $this->assertEquals($this->playlist->getId(), $playlists[0]->getId());
    }
}
