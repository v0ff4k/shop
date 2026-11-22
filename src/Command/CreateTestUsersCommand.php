<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Создание тестовых пользователей.
 *
 * Использование:
 *   php bin/console app:create-test-users              # интерактивно
 *   php bin/console app:create-test-users --force      # без подтверждения
 *
 * ⚠️  НЕ используйте на production без флага --force!
 */
#[AsCommand(
    name: 'app:create-test-users',
    description: 'Создание тестовых пользователей (admin, user, seller, guest)'
)]
final class CreateTestUsersCommand extends Command
{
    private const TEST_USERS = [
        [
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'adminpass',
            'roles' => ['ROLE_ADMIN'],
            'fullName' => 'Administrator',
        ],
        [
            'username' => 'user',
            'email' => 'user@example.com',
            'password' => 'userpass',
            'roles' => ['ROLE_CUSTOMER'],
            'fullName' => 'Test User',
        ],
        [
            'username' => 'seller',
            'email' => 'seller@example.com',
            'password' => 'sellerpass',
            'roles' => ['ROLE_SELLER'],
            'fullName' => 'Test Seller',
        ],
        [
            'username' => 'guest',
            'email' => 'guest@example.com',
            'password' => 'guestpass',
            'roles' => ['ROLE_CUSTOMER'],
            'fullName' => 'Guest User',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<HELP
Создаёт тестовых пользователей для разработки:

  <info>admin@example.com</info>  / пароль: <comment>adminpass</comment>  (ROLE_ADMIN)
  <info>user@example.com</info>   / пароль: <comment>userpass</comment>   (ROLE_CUSTOMER)
  <info>seller@example.com</info> / пароль: <comment>sellerpass</comment> (ROLE_SELLER)
  <info>guest@example.com</info>  / пароль: <comment>guestpass</comment>  (ROLE_CUSTOMER)

Используйте <comment>--force</comment> для пропуска подтверждения.
HELP
            )
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Пропустить подтверждение');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        // Предупреждение на production
        if ($input->getOption('env') !== 'dev' && !$force) {
            $io->warning('Вы запускаете команду НЕ В DEV окружении!');
            $io->warning('Это создаст тестовых пользователей с известными паролями.');
            if (!$io->confirm('Продолжить?', false)) {
                $io->note('Отменено пользователем');

                return Command::SUCCESS;
            }
        }

        $io->title('Создание тестовых пользователей');

        $created = 0;
        $skipped = 0;

        foreach (self::TEST_USERS as $userData) {
            $existing = $this->em->getRepository(User::class)->findOneBy([
                'email' => $userData['email'],
            ]) ?? $this->em->getRepository(User::class)->findOneBy([
                'username' => $userData['username'],
            ]);

            if ($existing) {
                $io->text("⏭  {$userData['username']} ({$userData['email']}) — уже существует");
                ++$skipped;
                continue;
            }

            $user = new User(
                username: $userData['username'],
                email: $userData['email'],
                plainPassword: $userData['password'],
                roles: $userData['roles']
            );
            $user->setFullName($userData['fullName']);

            $this->em->persist($user);
            $this->em->flush();

            $io->success(" {$userData['username']} / {$userData['email']} / пароль: {$userData['password']}");
            ++$created;
        }

        $io->newLine();
        $io->success("Готово! Создано: $created, пропущено: $skipped");

        return Command::SUCCESS;
    }
}
