<?php

declare(strict_types=1);

namespace App\Infrastructure\Jwt;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Symfony\Component\Clock\NativeClock;

/**
 * Сервис для работы с JWT токенами.
 */
final class JwtService
{
    private Configuration $config;

    public function __construct(
        string $secret,
        private readonly int $ttl = 3600,
        private readonly int $refreshTtl = 604800
    ) {
        $signingSecret = hash('sha256', $secret, true);

        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($signingSecret)
        );

        $this->config->setValidationConstraints(
            new SignedWith(new Sha256(), InMemory::plainText($signingSecret)),
            new LooseValidAt(new NativeClock())
        );
    }

    /**
     * Создать JWT токен.
     *
     * @param array<string, mixed> $claims Дополнительные claims
     */
    public function createToken(string $userIdentifier, array $claims = []): string
    {
        $now = new \DateTimeImmutable();

        $builder = $this->config->builder(
            ChainedFormatter::withUnixTimestampDates(),
            new JoseEncoder()
        )
            ->issuedBy('shop-app')
            ->permittedFor('shop-client')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$this->ttl} seconds"))
            ->relatedTo($userIdentifier)
            ->identifiedBy(bin2hex(random_bytes(16)));

        foreach ($claims as $key => $value) {
            $builder = $builder->withClaim($key, $value);
        }

        return $builder
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    /**
     * Создать refresh токен.
     */
    public function createRefreshToken(string $userIdentifier): string
    {
        $now = new \DateTimeImmutable();

        return $this->config->builder(
            ChainedFormatter::withUnixTimestampDates(),
            new JoseEncoder()
        )
            ->issuedBy('shop-app')
            ->permittedFor('shop-client')
            ->issuedAt($now)
            ->expiresAt($now->modify("+{$this->refreshTtl} seconds"))
            ->relatedTo($userIdentifier)
            ->withClaim('type', 'refresh')
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    /**
     * Проверить и получить claims из токена.
     *
     * @throws RequiredConstraintsViolated
     *
     * @return array<string, mixed>
     */
    public function parseToken(string $token): array
    {
        $jwt = $this->config->parser()->parse($token);

        $this->config->validator()->assert($jwt, ...$this->config->validationConstraints());

        return [
            'user' => $jwt->claims()->get('sub'),
            'jti' => $jwt->claims()->get('jti'),
            'type' => $jwt->claims()->get('type', null),
            'exp' => $jwt->claims()->get('exp'),
            'iat' => $jwt->claims()->get('iat'),
        ];
    }

    /**
     * Проверить валидность токена.
     */
    public function validateToken(string $token): bool
    {
        try {
            $jwt = $this->config->parser()->parse($token);
            $this->config->validator()->assert($jwt, ...$this->config->validationConstraints());

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Получить оставшееся время жизни токена в секундах.
     */
    public function getTokenRemainingTtl(string $token): int
    {
        try {
            $jwt = $this->config->parser()->parse($token);
            $exp = $jwt->claims()->get('exp');

            if (!$exp instanceof \DateTimeImmutable) {
                return 0;
            }

            return max(0, $exp->getTimestamp() - time());
        } catch (\Exception) {
            return 0;
        }
    }
}
