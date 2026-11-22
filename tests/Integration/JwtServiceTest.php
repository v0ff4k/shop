<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Jwt\JwtService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JwtServiceTest extends KernelTestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->jwtService = self::getContainer()->get(JwtService::class);
    }

    public function testCreateAndParseToken(): void
    {
        $token = $this->jwtService->createToken('test@example.com', [
            'user_id' => 123,
            'roles' => ['ROLE_USER'],
        ]);

        $this->assertSame(3, \count(explode('.', $token)));
        $this->assertTrue($this->jwtService->validateToken($token));

        $claims = $this->jwtService->parseToken($token);

        $this->assertSame('test@example.com', $claims['user']);
        $this->assertIsString($claims['jti']);
        $this->assertNotSame('', $claims['jti']);
        $this->assertNull($claims['type']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $claims['iat']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $claims['exp']);
        $this->assertGreaterThan(0, $this->jwtService->getTokenRemainingTtl($token));
    }

    public function testValidateExpiredTokenReturnsFalse(): void
    {
        $jwtService = new JwtService('test_secret_key', ttl: 1, refreshTtl: 60);

        $token = $jwtService->createToken('test@example.com');

        // Ждем истечения токена
        sleep(2);

        $this->assertFalse($jwtService->validateToken($token));
        $this->assertSame(0, $jwtService->getTokenRemainingTtl($token));
    }

    public function testValidateTokenWithWrongKeyReturnsFalse(): void
    {
        $issuer = new JwtService('correct_key');
        $verifier = new JwtService('wrong_key');

        $token = $issuer->createToken('test@example.com');

        $this->assertTrue($issuer->validateToken($token));
        $this->assertFalse($verifier->validateToken($token));
    }

    public function testValidateMalformedTokenReturnsFalse(): void
    {
        $malformedToken = 'invalid.token.string';

        $this->assertFalse($this->jwtService->validateToken($malformedToken));
    }

    public function testCreateRefreshToken(): void
    {
        $token = $this->jwtService->createRefreshToken('test@example.com');

        $this->assertSame(3, \count(explode('.', $token)));
        $this->assertTrue($this->jwtService->validateToken($token));

        $claims = $this->jwtService->parseToken($token);

        $this->assertSame('test@example.com', $claims['user']);
        $this->assertSame('refresh', $claims['type']);
        $this->assertGreaterThan(
            $this->jwtService->getTokenRemainingTtl($this->jwtService->createToken('test@example.com')),
            $this->jwtService->getTokenRemainingTtl($token)
        );
    }
}
