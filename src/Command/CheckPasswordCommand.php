<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\User\Entity\User;
use App\Infrastructure\Persistence\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:check-password', description: 'Проверка пароля пользователя и конфигурации')]
final class CheckPasswordCommand extends Command
{
    private UserPasswordHasherInterface $passwordHasher;
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    public function __construct(UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, UserRepository $userRepository)
    {
        parent::__construct();
        $this->passwordHasher = $passwordHasher;
        $this->em = $em;
        $this->userRepository = $userRepository;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username или email пользователя')
            ->addArgument('password', InputArgument::OPTIONAL, 'Пароль для проверки')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Проверка конфигурации паролей');

        $io->section('Переменные окружения');
        $io->table(['Параметр', 'Значение'], [
            ['APP_SECRET', $_ENV['APP_SECRET'] ?? 'not set'],
            ['APP_ENV', $_ENV['APP_ENV'] ?? 'not set'],
        ]);

        $username = $input->getArgument('username');

        $user = $this->userRepository->findOneByUsernameOrEmail($username);

        if (!$user) {
            $io->error("Пользователь '$username' не найден!");

            $io->section('Доступные пользователи');
            $users = $this->em->createQueryBuilder()
                ->select('u.username, u.email')
                ->from(User::class, 'u')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            $table = [];
            foreach ($users as $u) {
                $table[] = [$u['username'], $u['email']];
            }
            $io->table(['Username', 'Email'], $table);

            return Command::FAILURE;
        }

        $io->section('Информация о пользователе');
        $io->table(['Поле', 'Значение'], [
            ['Username', $user->getUsername()],
            ['Email', $user->getEmail()],
            ['Password hash', substr($user->getPasswordHash(), 0, 50).'...'],
            ['Is Active', $user->isEnabled() ? 'Да' : 'Нет'],
        ]);

        $password = $input->getArgument('password');
        if ($password) {
            $io->section('Проверка пароля');
            $isValid = $this->passwordHasher->isPasswordValid($user, $password);

            if ($isValid) {
                $io->success("Пароль '$password' ВАЛИДЕН для пользователя '{$user->getUsername()}'");
            } else {
                $io->error("Пароль '$password' НЕВЕРНЫЙ для пользователя '{$user->getUsername()}'");
                $io->note('Попробуйте создать новый хеш: php bin/console security:hash-password "ваш_пароль"');
            }
        } else {
            $io->note("Укажите пароль для проверки: php bin/console app:check-password $username ваш_пароль");
        }

        $io->section('Информация о хешере');
        $hasherClass = \get_class($this->passwordHasher);
        $io->text("Class: $hasherClass");

        return Command::SUCCESS;
    }
}
