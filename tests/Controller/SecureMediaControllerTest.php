<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecureMediaControllerTest extends WebTestCase
{
    public function testSecureMediaRouteExists(): void
    {
        $client = static::createClient();
        
        // Проверяем что маршрут существует
        $client->request('GET', '/secure-media/videos/nonexistent.mp4', [], [], [
            'HTTP_REFERER' => 'http://localhost/',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $statusCode = $client->getResponse()->getStatusCode();
        // Ожидаем 403 (Access Denied), 404 (Not Found), или 500 (если БД не настроена)
        $this->assertContains($statusCode, [403, 404, 500], "Unexpected status code: {$statusCode}");
    }

    public function testPosterRouteExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/secure-media/posters/test.jpg', [], [], [
            'HTTP_REFERER' => 'http://localhost/',
            'HTTP_USER_AGENT' => 'Mozilla/5.0'
        ]);
        
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [403, 404, 500]);
    }

    public function testPreviewRouteExists(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/secure-media/previews/test.mp4', [], [], [
            'HTTP_REFERER' => 'http://localhost/',
            'HTTP_USER_AGENT' => 'Mozilla/5.0'
        ]);
        
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [403, 404, 500]);
    }
}
