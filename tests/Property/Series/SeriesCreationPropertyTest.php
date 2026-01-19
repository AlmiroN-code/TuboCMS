<?php

declare(strict_types=1);

namespace App\Tests\Property\Series;

use App\Entity\Series;
use App\Entity\User;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for video series creation functionality.
 * 
 * **Feature: user-engagement-features, Property 44: Video Series Creation**
 * **Validates: Requirements 12.1**
 * 
 * Property: Для любого автора создающего серию с валидными данными, система должна создать
 * контейнер серии с указанными названием, описанием и обложкой.
 */
class SeriesCreationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property: Серия создаётся с корректным названием
     * 
     * Для любого валидного названия серии (непустая строка до 200 символов),
     * созданная серия должна содержать это название.
     */
    public function testSeriesCreatedWithCorrectTitle(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $title): void {
            $author = $this->createUser();
            $series = $this->createSeries($author, $title);
            
            $this->assertEquals(
                $title,
                $series->getTitle(),
                'Series title should match the provided title'
            );
        });
    }

    /**
     * Property: Серия создаётся с корректным описанием
     * 
     * Для любого описания (включая null), созданная серия должна содержать это описание.
     */
    public function testSeriesCreatedWithCorrectDescription(): void
    {
        $this->forAll(
            Generator\oneOf(
                Generator\constant(null),
                Generator\string()
            )
        )->withMaxSize(100)->then(function (?string $description): void {
            $author = $this->createUser();
            $series = $this->createSeries($author, 'Test Series', $description);
            
            $this->assertEquals(
                $description,
                $series->getDescription(),
                'Series description should match the provided description'
            );
        });
    }

    /**
     * Property: Серия создаётся с корректным автором
     * 
     * Для любого пользователя, созданная серия должна иметь этого пользователя как автора.
     */
    public function testSeriesCreatedWithCorrectAuthor(): void
    {
        $this->forAll(
            Generator\choose(1, 10000)
        )->withMaxSize(100)->then(function (int $userId): void {
            $author = $this->createUserWithId($userId);
            $series = $this->createSeries($author, 'Test Series');
            
            $this->assertSame(
                $author,
                $series->getAuthor(),
                'Series author should be the user who created it'
            );
        });
    }

    /**
     * Property: Серия создаётся с корректной обложкой
     * 
     * Для любого пути к обложке (включая null), созданная серия должна содержать этот путь.
     */
    public function testSeriesCreatedWithCorrectThumbnail(): void
    {
        $this->forAll(
            Generator\oneOf(
                Generator\constant(null),
                Generator\suchThat(
                    fn(string $s) => strlen($s) > 0 && strlen($s) <= 255,
                    Generator\string()
                )
            )
        )->withMaxSize(100)->then(function (?string $thumbnail): void {
            $author = $this->createUser();
            $series = $this->createSeries($author, 'Test Series', null, $thumbnail);
            
            $this->assertEquals(
                $thumbnail,
                $series->getThumbnail(),
                'Series thumbnail should match the provided thumbnail'
            );
        });
    }

    /**
     * Property: Новая серия имеет нулевой счётчик видео
     * 
     * Для любой новой серии, счётчик видео должен быть равен 0.
     */
    public function testNewSeriesHasZeroVideosCount(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $title): void {
            $author = $this->createUser();
            $series = $this->createSeries($author, $title);
            
            $this->assertEquals(
                0,
                $series->getVideosCount(),
                'New series should have zero videos count'
            );
        });
    }

    /**
     * Property: Новая серия имеет пустую коллекцию сезонов
     * 
     * Для любой новой серии, коллекция сезонов должна быть пустой.
     */
    public function testNewSeriesHasEmptySeasons(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $title): void {
            $author = $this->createUser();
            $series = $this->createSeries($author, $title);
            
            $this->assertCount(
                0,
                $series->getSeasons(),
                'New series should have empty seasons collection'
            );
        });
    }

    /**
     * Property: Новая серия имеет корректную временную метку создания
     * 
     * Для любой новой серии, createdAt должен быть установлен.
     */
    public function testNewSeriesHasCreatedAtTimestamp(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 200,
                Generator\string()
            )
        )->withMaxSize(100)->then(function (string $title): void {
            $author = $this->createUser();
            $series = $this->createSeries($author, $title);
            
            $this->assertNotNull(
                $series->getCreatedAt(),
                'New series should have createdAt timestamp'
            );
        });
    }

    /**
     * Property: Все атрибуты серии сохраняются корректно
     * 
     * Для любой комбинации валидных данных, все атрибуты должны быть сохранены корректно.
     */
    public function testSeriesCreationConsistency(): void
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
            Generator\oneOf(
                Generator\constant(null),
                Generator\suchThat(
                    fn(string $s) => strlen($s) > 0 && strlen($s) <= 255,
                    Generator\string()
                )
            ),
            Generator\choose(1, 10000)
        )->withMaxSize(100)->then(function (string $title, ?string $description, ?string $thumbnail, int $userId): void {
            $author = $this->createUserWithId($userId);
            $series = $this->createSeries($author, $title, $description, $thumbnail);
            
            // Проверяем все атрибуты
            $this->assertEquals($title, $series->getTitle(), 'Title mismatch');
            $this->assertEquals($description, $series->getDescription(), 'Description mismatch');
            $this->assertEquals($thumbnail, $series->getThumbnail(), 'Thumbnail mismatch');
            $this->assertSame($author, $series->getAuthor(), 'Author mismatch');
            $this->assertEquals(0, $series->getVideosCount(), 'Videos count should be 0');
            $this->assertCount(0, $series->getSeasons(), 'Seasons should be empty');
            $this->assertNotNull($series->getCreatedAt(), 'CreatedAt should be set');
        });
    }

    /**
     * Property: Slug генерируется корректно
     * 
     * Для любого slug, установленный slug должен сохраняться корректно.
     */
    public function testSeriesSlugIsSetCorrectly(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn(string $s) => strlen($s) > 0 && strlen($s) <= 250 && preg_match('/^[a-z0-9-]+$/', $s),
                Generator\map(
                    fn(string $s) => strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $s)),
                    Generator\string()
                )
            )
        )->withMaxSize(100)->then(function (string $slug): void {
            $author = $this->createUser();
            $series = $this->createSeries($author, 'Test Series');
            $series->setSlug($slug);
            
            $this->assertEquals(
                $slug,
                $series->getSlug(),
                'Series slug should match the provided slug'
            );
        });
    }

    /**
     * Создаёт серию с заданными параметрами
     */
    private function createSeries(
        User $author,
        string $title,
        ?string $description = null,
        ?string $thumbnail = null
    ): Series {
        $series = new Series();
        $series->setAuthor($author);
        $series->setTitle($title);
        $series->setDescription($description);
        $series->setThumbnail($thumbnail);
        $series->setSlug('test-series-' . uniqid());
        
        return $series;
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
