<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\User;
use App\Repository\UserRepository;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/**
 * JWT-аутентификация (HS256).
 */
final class AuthService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly UserRepository $users,
        private readonly string $secret,
        private readonly int $ttl,
    ) {
    }

    /** @return array{token: string, user: array<string, mixed>} */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->verifyPassword($password)) {
            throw new RuntimeException('Неверный email или пароль', 401);
        }

        return [
            'token' => $this->issueToken($user),
            'user' => $user->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function userFromToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (ExpiredException) {
            throw new RuntimeException('Срок действия токена истёк', 401);
        } catch (\Throwable) {
            throw new RuntimeException('Невалидный токен', 401);
        }

        /** @var array<string, mixed> $data */
        $data = (array) $decoded;

        $sub = $data['sub'] ?? null;
        if (!\is_int($sub)) {
            throw new RuntimeException('Невалидный токен', 401);
        }

        return [
            'id' => $sub,
            'email' => \is_string($data['email'] ?? null) ? $data['email'] : '',
            'name' => \is_string($data['name'] ?? null) ? $data['name'] : '',
        ];
    }

    public function extractToken(string $authorizationHeader): string
    {
        if (!str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new RuntimeException('Требуется заголовок Authorization: Bearer <token>', 401);
        }

        return trim(substr($authorizationHeader, 7));
    }

    private function issueToken(User $user): string
    {
        $issuedAt = new \DateTimeImmutable();

        return JWT::encode(
            [
                'iss' => 'products-import',
                'sub' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'iat' => $issuedAt->getTimestamp(),
                'exp' => $issuedAt->getTimestamp() + $this->ttl,
            ],
            $this->secret,
            self::ALGORITHM,
        );
    }
}
