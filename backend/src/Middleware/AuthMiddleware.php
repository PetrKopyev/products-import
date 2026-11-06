<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuthService;
use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * JWT-аутентификация: Authorization: Bearer <token> → атрибут request 'user'.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        try {
            $user = $this->auth->userFromToken($this->auth->extractToken($header));
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 401);
        }

        return $handler->handle($request->withAttribute('user', $user));
    }
}
