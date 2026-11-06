<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

/**
 * Скачивание изображений товаров по URL из xlsx.
 *
 * Файлы складываются в {storagePath}/images/{md5}.{ext}.
 * Ошибки одного изображения не влияют на остальные.
 *
 * (не final — в тестах подменяется заглушкой без сети)
 */
class ImageDownloadService
{
    private const CONCURRENCY = 5;

    private readonly Client $client;
    private readonly string $imagesDir;

    public function __construct(
        private readonly string $storagePath,
        private readonly int $maxBytes,
        private readonly int $timeout,
    ) {
        $this->imagesDir = rtrim($storagePath, '/') . '/images';
        $this->client = new Client([
            'timeout' => $timeout,
            'connect_timeout' => 5,
            'http_errors' => false,
            'verify' => true,
        ]);
    }

    /**
     * @param array<string> $urls
     * @return array<string, array{url: string, path: string|null, error: string|null}> ключ — исходный URL
     */
    public function downloadAll(array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        if (!is_dir($this->imagesDir)) {
            mkdir($this->imagesDir, 0o775, true);
        }

        $results = array_fill_keys($urls, ['url' => '', 'path' => null, 'error' => null]);
        foreach ($urls as $url) {
            $results[$url] = ['url' => $url, 'path' => null, 'error' => null];
        }

        $requests = function () use ($urls) {
            foreach ($urls as $url) {
                yield new Request('GET', $url);
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => self::CONCURRENCY,
            'fulfilled' => function ($response, $index) use ($urls, &$results): void {
                $url = $urls[$index];
                /** @var \Psr\Http\Message\ResponseInterface $response */
                $body = (string) $response->getBody();
                $contentType = strtolower($response->getHeaderLine('Content-Type'));

                if ($response->getStatusCode() !== 200) {
                    $results[$url]['error'] = sprintf('HTTP %d', $response->getStatusCode());

                    return;
                }

                if ($body === '') {
                    $results[$url]['error'] = 'Пустой ответ';

                    return;
                }

                if ($contentType !== '' && !str_starts_with($contentType, 'image/')) {
                    $results[$url]['error'] = sprintf('Не изображение (Content-Type: %s)', $contentType);

                    return;
                }

                if (\strlen($body) > $this->maxBytes) {
                    $results[$url]['error'] = 'Превышен максимальный размер изображения';

                    return;
                }

                $filename = $this->buildFilename($url);
                if (file_put_contents($this->imagesDir . '/' . $filename, $body) === false) {
                    $results[$url]['error'] = 'Не удалось сохранить файл';

                    return;
                }

                $results[$url]['path'] = $filename;
            },
            'rejected' => function ($reason, $index) use ($urls, &$results): void {
                $url = $urls[$index];
                if ($reason instanceof GuzzleException) {
                    $results[$url]['error'] = $reason->getMessage();
                } else {
                    $results[$url]['error'] = 'Ошибка скачивания';
                }
            },
        ]);

        $pool->promise()->wait();

        return $results;
    }

    private function buildFilename(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!\in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            $extension = 'jpg';
        }

        return md5($url) . '.' . $extension;
    }
}
