<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Entity\User;
use App\Support\Bootstrap;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected static App $app;
    protected static ContainerInterface $container;
    protected static EntityManager $em;

    /** @var array<string, string> кэш токенов по email */
    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$app = Bootstrap::createApp(static::containerOverrides());
        self::$container = self::$app->getContainer();
        self::$em = self::$container->get(EntityManager::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetDatabase();
        $this->seedAdmin();
    }

    /**
     * Подмены определений контейнера для конкретного тестового класса.
     *
     * @return array<string, mixed>
     */
    protected static function containerOverrides(): array
    {
        return [];
    }

    protected function resetDatabase(): void
    {
        $schemaTool = new SchemaTool(self::$em);
        $metadata = self::$em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        self::$em->clear();
    }

    protected function seedAdmin(): void
    {
        $user = new User(
            email: 'admin@example.com',
            passwordHash: password_hash('admin123', PASSWORD_DEFAULT),
            name: 'Тест Админ',
        );
        self::$em->persist($user);
        self::$em->flush();
    }

    protected function authToken(string $email = 'admin@example.com', string $password = 'admin123'): string
    {
        $key = $email . ':' . $password;
        if (!isset(self::$tokens[$key])) {
            $response = $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
            self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            /** @var array{token: string} $payload */
            $payload = $this->decode($response);
            self::$tokens[$key] = $payload['token'];
        }

        return self::$tokens[$key];
    }

    protected function jsonRequest(
        string $method,
        string $uri,
        ?array $body = null,
        array $headers = [],
        array $serverParams = ['REMOTE_ADDR' => '127.0.0.1'],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri, $serverParams);

        if ($body !== null) {
            $request = $request
                ->withBody((new StreamFactory())->createStream((string) json_encode($body)))
                ->withHeader('Content-Type', 'application/json');
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return self::$app->handle($request);
    }

    protected function uploadRequest(
        string $uri,
        string $filePath,
        string $clientFilename,
        string $mediaType,
        array $headers = [],
        array $serverParams = ['REMOTE_ADDR' => '127.0.0.1'],
    ): ResponseInterface {
        // Контроллер перемещает загруженный файл (moveTo), поэтому заливаем копию
        $copyPath = tempnam(sys_get_temp_dir(), 'upload-test-');
        self::assertNotFalse($copyPath);
        copy($filePath, $copyPath);

        $stream = (new StreamFactory())->createStreamFromFile($copyPath, 'rb');
        $uploadedFile = new UploadedFile(
            $stream,
            $clientFilename,
            $mediaType,
            filesize($copyPath) ?: null,
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $uri, $serverParams)
            ->withUploadedFiles(['file' => $uploadedFile]);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            return self::$app->handle($request);
        } finally {
            if (file_exists($copyPath)) {
                unlink($copyPath);
            }
        }
    }

    /** @return array<string, mixed>|array<int, mixed> */
    protected function decode(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return \is_array($decoded) ? $decoded : [];
    }
}
