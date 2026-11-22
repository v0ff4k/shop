<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Command\IdempotentCommandInterface;
use App\Infrastructure\Idempotency\IdempotencyMiddleware;
use App\Infrastructure\Idempotency\RedisIdempotencyChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class IdempotencyServiceTest extends KernelTestCase
{
    private \Redis $redis;
    private RedisIdempotencyChecker $checker;
    private IdempotencyMiddleware $middleware;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->redis = $container->get(\Redis::class);
        $this->checker = $container->get(RedisIdempotencyChecker::class);
        $this->middleware = $container->get(IdempotencyMiddleware::class);

        // Очищаем ключи идемпотентности перед каждым тестом
        $this->cleanupIdempotencyKeys();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupIdempotencyKeys();
    }

    public function testIdempotencyCheckerNewKey(): void
    {
        $key = 'test_key_'.uniqid();

        $this->assertFalse($this->checker->isDuplicate($key));
        $this->assertNull($this->checker->getProcessedResult($key));

        $this->checker->markAsProcessed($key, ['status' => 'ok']);

        $this->assertTrue($this->checker->isDuplicate($key));
        $this->assertSame(['status' => 'ok'], $this->checker->getProcessedResult($key));
    }

    public function testIdempotencyCheckerTtl(): void
    {
        $key = 'test_ttl_key_'.uniqid();
        $ttl = 2; // 2 seconds

        $this->checker->markAsProcessed($key, 'result', $ttl);

        $this->assertTrue($this->checker->isDuplicate($key));

        sleep(3);

        $this->assertFalse($this->checker->isDuplicate($key));
        $this->assertNull($this->checker->getProcessedResult($key));
    }

    public function testIdempotencyMiddlewareExecutesCommandOnce(): void
    {
        $command = $this->createMock(IdempotentCommandInterface::class);
        $command->method('getIdempotencyKey')->willReturn('test_middleware_'.uniqid());

        $envelope = new Envelope($command);
        $handledEnvelope = $envelope->with(new HandledStamp('command_result', 'handler'));

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->isInstanceOf(StackInterface::class))
            ->willReturn($handledEnvelope);

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        // Первый вызов - команда выполняется и результат кэшируется
        $resultEnvelope = $this->middleware->handle($envelope, $stack);
        $this->assertSame('command_result', $resultEnvelope->last(HandledStamp::class)?->getResult());

        // Второй вызов с тем же ключом - команда повторно не выполняется
        $cachedEnvelope = $this->middleware->handle($envelope, $stack);
        $this->assertSame('command_result', $cachedEnvelope->last(HandledStamp::class)?->getResult());
        $this->assertSame($command, $cachedEnvelope->getMessage());
    }

    public function testIdempotencyMiddlewareSkipsMessagesWithoutKey(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->expects($this->once())
            ->method('handle')
            ->willReturn($envelope);

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        $result = $this->middleware->handle($envelope, $stack);

        $this->assertSame($envelope, $result);
    }

    private function cleanupIdempotencyKeys(): void
    {
        $keys = $this->redis->keys('idempotency:*');
        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }
}
