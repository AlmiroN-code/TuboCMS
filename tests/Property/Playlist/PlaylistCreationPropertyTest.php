<?php

declare(strict_types=1);

namespace App\Tests\Property\Playlist;

use App\Entity\Playlist;
use App\Entity\User;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for playlist creation functionality.
 * 
 * **Feature: user-engagement-features, Property 1: Playlist Creation Consistency**
 * **Validates: Requirements 1.1**
 * 
 * Property: Для любого пользователя и валидных данных плейлиста (название, описание, приватность),
 * создание плейлиста должно результировать в сохранении плейлиста с правильными атрибутами и владельцем.
 */
class PlaylistCreationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Плейлист создаётся с корректным названием
     * 
     * Для любого валидного названия плейлиста (непустая строка до 200 символов),
     * созданный плейлист должен содержать это название.
     */
    public function testPlaylistCreatedWithCorrectTitle(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $title): void {
            $user = $this->createUser();
            $playlist = $this->createPlaylist($user, $title);
            
            $this->assertEquals(
                $title,
                $playlist->getTitle(),
                'Playlist title should match the provided title'
            );
        });
    }

    /**
     * Property: Плейлист создаётся с корректным описанием
     * 
     * Для любого описания (включая null), созданный плейлист должен содержать это описание.
     */
    public function testPlaylistCreatedWithCorrectDescription(): void
    {
        $this->forAll(
            Generator\oneOf(
                Generator\constant(null),
                Generator\string()
            )
        )->withMaxSize(100)->then(function (?string $description): void {
            $user = $this->createUser();
            $playlist = $this->createPlaylist($user, 'Test Playlist', $description);
            
            $this->assertEquals(
                $description,
                $playlist->getDescription(),
                'Playlist description should match the provided description'
            );
        });
    }

    /**
     * Property: Плейлист создаётся с корректной приватностью
     * 
     * Для любого значения приватности (true/false), созданный плейлист должен иметь это значение.
     */
    public function testPlaylistCreatedWithCorrectPrivacy(): void
    {
        $this->forAll(
            Generator\bool()
        )->withMaxSize(100)->then(function (bool $isPublic): void {
            $user = $this->createUser();
            $playlist = $this->createPlaylist($user, 'Test Playlist', null, $isPublic);
            
            $this->assertEquals(
                $isPublic,
                $playlist->isPublic(),
                'Playlist privacy should match the provided value'
            );
        });
    }

    /**
     * Property: Плейлист создаётся с корректным владельцем
     * 
     * Для любого пользователя, созданный плейлист должен иметь этого пользователя как владельца.
     */
    public function testPlaylistCreatedWithCorrectOwner(): void
    {
        $this->forAll(
            Generator\choose(1, 10000)
        )->withMaxSize(100)->then(function (int $userId): void {
            $user = $this->createUserWithId($userId);
            $playlist = $this->createPlaylist($user, 'Test Playlist');
            
            $this->assertSame(
                $user,
                $playlist->getOwner(),
                'Playlist owner should be the user who created it'
            );
        });
    }

    /**
     * Property: Новый плейлист имеет нулевой счётчик видео
     * 
     * Для любого нового плейлиста, счётчик видео должен быть равен 0.
     */
    public function testNewPlaylistHasZeroVideosCount(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $title): void {
            $user = $this->createUser();
            $playlist = $this->createPlaylist($user, $title);
            
            $this->assertEquals(
                0,
                $playlist->getVideosCount(),
                'New playlist should have zero videos count'
            );
        });
    }

    /**
     * Property: Новый плейлист имеет корректные временные метки
     * 
     * Для любого нового плейлиста, createdAt и updatedAt должны быть установлены.
     */
    public function testNewPlaylistHasTimestamps(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $title): void {
            $user = $this->createUser();
            $playlist = $this->createPlaylist($user, $title);
            
            $this->assertNotNull(
                $playlist->getCreatedAt(),
                'New playlist should have createdAt timestamp'
            );
            $this->assertNotNull(
                $playlist->getUpdatedAt(),
                'New playlist should have updatedAt timestamp'
            );
        });
    }

    /**
     * Property: Все атрибуты плейлиста сохраняются корректно
     * 
     * Для любой комбинации валидных данных, все атрибуты должны быть сохранены корректно.
     */
    public function testPlaylistCreationConsistency(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            ),
            Generator\oneOf(
                Generator\constant(null),
                Generator\string()
            ),
            Generator\bool(),
            Generator\choose(1, 10000)
        )->withMaxSize(100)->then(function (string $title, ?string $description, bool $isPublic, int $userId): void {
            $user = $this->createUserWithId($userId);
            $playlist = $this->createPlaylist($user, $title, $description, $isPublic);
            
            // Проверяем все атрибуты
            $this->assertEquals($title, $playlist->getTitle(), 'Title mismatch');
            $this->assertEquals($description, $playlist->getDescription(), 'Description mismatch');
            $this->assertEquals($isPublic, $playlist->isPublic(), 'Privacy mismatch');
            $this->assertSame($user, $playlist->getOwner(), 'Owner mismatch');
            $this->assertEquals(0, $playlist->getVideosCount(), 'Videos count should be 0');
            $this->assertNotNull($playlist->getCreatedAt(), 'CreatedAt should be set');
            $this->assertNotNull($playlist->getUpdatedAt(), 'UpdatedAt should be set');
        });
    }

    /**
     * Создаёт плейлист с заданными параметрами
     */
    private function createPlaylist(
        User $user,
        string $title,
        ?string $description = null,
        bool $isPublic = true
    ): Playlist {
        $playlist = new Playlist();
        $playlist->setOwner($user);
        $playlist->setTitle($title);
        $playlist->setDescription($description);
        $playlist->setIsPublic($isPublic);
        
        return $playlist;
    }

    /**
     * Создаёт тестового пользователя
     */
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password');
        
        return $user;
    }

    /**
     * Создаёт тестового пользователя с заданным ID
     */
    private function createUserWithId(int $id): User
    {
        $user = new User();
        $user->setEmail("test{$id}@example.com");
        $user->setUsername("testuser{$id}");
        $user->setPassword('password');
        
        // Устанавливаем ID через рефлексию
        $reflection = new \ReflectionClass($user);
        $prop = $reflection->getProperty('id');
        $prop->setValue($user, $id);
        
        return $user;
    }
}
