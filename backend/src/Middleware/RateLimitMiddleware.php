<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rate limiting по IP (скользящее окно, в памяти процесса).
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var array<string, array<int>> IP => timestamps запросов */
    private static array $hits = [];

    public function __construct(
        private readonly int $maxRequests,
        private readonly int $periodSeconds,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $now = time();

        $window = array_filter(
            self::$hits[$ip] ?? [],
            fn (int $ts): bool => $ts > $now - $this->periodSeconds,
        );

        if (\count($window) >= $this->maxRequests) {
            throw new ApiException(
                sprintf('Слишком много запросов (лимит: %d за %d сек). Попробуйте позже.', $this->maxRequests, $this->periodSeconds),
                429,
            );
        }

        $window[] = $now;
        self::$hits[$ip] = $window;

        $response = $handler->handle($request);

        return $response->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxRequests - \count($window)));
    }
}
