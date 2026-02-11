<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Генерирует VAPID ключи для Web Push уведомлений',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Генерация VAPID ключей для Web Push');

        // Проверяем доступность OpenSSL
        if (!extension_loaded('openssl')) {
            $io->error('Расширение OpenSSL не загружено в PHP');
            $this->showAlternativeMethod($io);
            return Command::FAILURE;
        }

        try {
            // Генерируем приватный ключ
            $config = [
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
            ];
            
            $privateKeyResource = openssl_pkey_new($config);
            if ($privateKeyResource === false) {
                $io->warning('Не удалось создать ключ через OpenSSL: ' . openssl_error_string());
                $this->showAlternativeMethod($io);
                return Command::FAILURE;
            }

            // Экспортируем приватный ключ
            openssl_pkey_export($privateKeyResource, $privateKeyPEM);
            
            // Получаем публичный ключ
            $publicKeyDetails = openssl_pkey_get_details($privateKeyResource);
            $publicKeyPEM = $publicKeyDetails['key'];

            // Конвертируем в base64url формат для VAPID
            $privateKeyBase64 = $this->convertPEMToBase64Url($privateKeyPEM, true);
            $publicKeyBase64 = $this->convertPEMToBase64Url($publicKeyPEM, false);

            $io->success('VAPID ключи успешно сгенерированы!');
            
            $io->section('Публичный ключ (Public Key)');
            $io->text($publicKeyBase64);
            $io->newLine();
            
            $io->section('Приватный ключ (Private Key)');
            $io->text($privateKeyBase64);
            $io->newLine();

            $this->showInstructions($io, $publicKeyBase64, $privateKeyBase64);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка при генерации ключей: ' . $e->getMessage());
            $this->showAlternativeMethod($io);
            return Command::FAILURE;
        }
    }

    private function showAlternativeMethod(SymfonyStyle $io): void
    {
        $io->section('Альтернативный способ генерации:');
        
        $io->text([
            '1. Используйте онлайн генератор:',
            '   https://web-push-codelab.glitch.me/',
            '',
            '2. Или используйте Node.js:',
            '   npx web-push generate-vapid-keys',
            '',
            '3. Или используйте тестовые ключи для разработки:',
        ]);

        // Тестовые ключи для разработки
        $testPublicKey = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';
        $testPrivateKey = 'UUxI4O8-FbRouAevSmBQ6o18hgE4nSG3qwvJTfKc-ls';

        $io->block([
            'VAPID_PUBLIC_KEY="' . $testPublicKey . '"',
            'VAPID_PRIVATE_KEY="' . $testPrivateKey . '"',
            'VAPID_SUBJECT="mailto:admin@rextube.test"',
        ], 'ТЕСТОВЫЕ КЛЮЧИ (только для разработки!)', 'fg=black;bg=yellow', ' ', true);

        $io->warning('Тестовые ключи НЕ безопасны для продакшена! Сгенерируйте свои ключи.');
    }

    private function showInstructions(SymfonyStyle $io, string $publicKey, string $privateKey): void
    {
        $io->section('Инструкция по использованию:');
        $io->listing([
            'Скопируйте публичный ключ в админку: /admin/pwa-settings',
            'Добавьте приватный ключ в .env файл:',
        ]);

        $io->block([
            'VAPID_PUBLIC_KEY="' . $publicKey . '"',
            'VAPID_PRIVATE_KEY="' . $privateKey . '"',
            'VAPID_SUBJECT="mailto:admin@rextube.test"',
        ], null, 'fg=black;bg=yellow', ' ', true);

        $io->note('Храните приватный ключ в секрете! Не добавляйте его в git.');
    }

    private function convertPEMToBase64Url(string $pem, bool $isPrivate): string
    {
        // Удаляем заголовки и переносы строк
        $pem = str_replace([
            '-----BEGIN EC PRIVATE KEY-----',
            '-----END EC PRIVATE KEY-----',
            '-----BEGIN PUBLIC KEY-----',
            '-----END PUBLIC KEY-----',
            "\r", "\n", ' '
        ], '', $pem);

        // Декодируем из base64
        $der = base64_decode($pem);

        if ($isPrivate) {
            // Извлекаем 32 байта приватного ключа из DER формата
            // Приватный ключ находится после определённых байтов в DER структуре
            $privateKeyHex = bin2hex($der);
            // Ищем приватный ключ (32 байта после маркера)
            if (preg_match('/0420([0-9a-f]{64})/', $privateKeyHex, $matches)) {
                $keyBytes = hex2bin($matches[1]);
            } else {
                // Альтернативный метод - берём последние 32 байта
                $keyBytes = substr($der, -32);
            }
        } else {
            // Для публичного ключа извлекаем 65 байт (несжатая точка EC)
            $publicKeyHex = bin2hex($der);
            if (preg_match('/0420([0-9a-f]{130})/', $publicKeyHex, $matches)) {
                $keyBytes = hex2bin('04' . $matches[1]);
            } else {
                // Альтернативный метод
                $keyBytes = substr($der, -65);
            }
        }

        // Конвертируем в base64url
        return rtrim(strtr(base64_encode($keyBytes), '+/', '-_'), '=');
    }
}
