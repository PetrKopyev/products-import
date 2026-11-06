<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OpenApiController
{
    private const SPEC_PATH = __DIR__ . '/../../openapi.yaml';

    public function swaggerUi(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="utf-8">
                <title>Products Import API — Swagger UI</title>
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
                <script>
                    window.onload = () => window.ui = SwaggerUIBundle({
                        url: '/api/openapi.yaml',
                        dom_id: '#swagger-ui',
                        persistAuthorization: true,
                    });
                </script>
            </body>
            </html>
            HTML;

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function spec(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $yaml = file_get_contents(self::SPEC_PATH);
        if ($yaml === false) {
            $response->getBody()->write('openapi.yaml не найден');

            return $response->withStatus(404);
        }

        $response->getBody()->write($yaml);

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }
}
