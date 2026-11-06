<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Возвращает ошибки API в виде JSON со своим HTTP-статусом.
 * Остальные исключения проходят в стандартный error handler Slim.
 */
final class ApiExceptionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ApiException $e) {
            $response = new \Slim\Psr7\Response(status: $e->getStatusCode());
            $response->getBody()->write((string) json_encode($e->payload(), JSON_UNESCAPED_UNICODE));

            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }
    }
}
