<?php

declare(strict_types=1);

namespace Tests\Application\Handler;

use App\Application\Command\SendEmailCommand;
use App\Application\Handler\SendEmailHandler;
use App\Infrastructure\Mailing\EmailSpoolService;
use App\Infrastructure\Redis\RedisLock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

final class SendEmailHandlerTest extends TestCase
{
    private string $spoolDir;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->spoolDir = sys_get_temp_dir().'/shop-email-spool-'.uniqid('', true);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->spoolDir);
    }

    public function testHandleSpoolsEmail(): void
    {
        $command = new SendEmailCommand(
            'test@example.com',
            'Test Subject',
            'Test Body'
        );

        $spoolService = new EmailSpoolService($this->spoolDir, true);
        $handler = new SendEmailHandler($this->mailer, $this->logger, $spoolService);
        $handler($command);

        $files = glob($this->spoolDir.'/var/spool/email/*.eml');
        self::assertIsArray($files);
        self::assertCount(1, $files);
    }

    public function testHandleSendsDirectlyWhenSpoolDisabled(): void
    {
        $command = new SendEmailCommand(
            'test@example.com',
            'Test Subject',
            'Test Body'
        );

        $spoolService = new EmailSpoolService($this->spoolDir, false);
        $this->mailer
            ->expects($this->once())
            ->method('send');

        $handler = new SendEmailHandler($this->mailer, $this->logger, $spoolService);
        $handler($command);
    }

    public function testHandleSkipsDuplicateWhileLockIsHeld(): void
    {
        $command = new SendEmailCommand(
            'test@example.com',
            'Test Subject',
            'Test Body'
        );

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('set')
            ->willReturn(false);

        $this->mailer->expects($this->never())->method('send');

        $spoolService = new EmailSpoolService($this->spoolDir, false);
        $handler = new SendEmailHandler(
            $this->mailer,
            $this->logger,
            $spoolService,
            new RedisLock($redis)
        );
        $handler($command);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $directory.'/'.$item;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    unlink($path);
                }
            }
        }

        rmdir($directory);
    }
}
