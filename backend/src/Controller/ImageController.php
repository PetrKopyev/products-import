<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Отдача скачанных изображений (в docker их отдаёт nginx напрямую с volume).
 */
final class ImageController
{
    public function __construct(private readonly string $storagePath)
    {
    }

    public function serve(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $filename = basename((string) ($args['filename'] ?? ''));
        if ($filename === '' || !preg_match('/^[a-z0-9._-]+$/i', $filename)) {
            throw new ApiException('Некорректное имя файла', 422);
        }

        $file = rtrim($this->storagePath, '/') . '/images/' . $filename;
        if (!is_file($file)) {
            throw new ApiException('Изображение не найдено', 404);
        }

        $contentType = mime_content_type($file);
        $stream = new \Slim\Psr7\Stream(fopen($file, 'rb'));

        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', \is_string($contentType) ? $contentType : 'application/octet-stream')
            ->withHeader('Content-Length', (string) filesize($file))
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
