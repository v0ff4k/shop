#!/usr/bin/env php
<?php
/**
 * Скрипт для проверки паролей пользователей
 * Использование: php bin/check-password.php username [password]
 */

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env')) {
    (new \Symfony\Component\Dotenv\Dotenv())->loadEnv(dirname(__DIR__) . '/.env');
}

$kernel = new App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();
$container = $kernel->getContainer()->get('service_container');

echo "=== Проверка пароля ===\n\n";

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine.orm.entity_manager');

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$username) {
    echo "Использование: php bin/check-password.php username [password]\n\n";
    
    echo "Существующие пользователи:\n";
    $users = $em->getRepository(User::class)->createQueryBuilder('u')
        ->select('u.username', 'u.email')
        ->setMaxResults(10)
        ->getQuery()
        ->getResult();
    
    foreach ($users as $u) {
        echo sprintf("  - %s (%s)\n", $u['username'], $u['email']);
    }
    exit(1);
}

// Ищем пользователя
$user = $em->getRepository(User::class)->createQueryBuilder('u')
    ->where('u.username = :q OR u.email = :q')
    ->setParameter('q', $username)
    ->getQuery()
    ->getOneOrNullResult();

if (!$user) {
    echo "✗ Пользователь '$username' не найден!\n";
    exit(1);
}

echo "Пользователь: {$user->getUsername()} ({$user->getEmail()})\n";
echo "Roles: " . implode(', ', $user->getRoles()) . "\n";
echo "Enabled: " . ($user->isEnabled() ? 'Да' : 'Нет') . "\n";
echo "Password hash: " . substr($user->getPassword(), 0, 60) . "...\n\n";

if (!$password) {
    echo "Укажите пароль: php bin/check-password.php $username ваш_пароль\n";
    exit(0);
}

// Создаём хэшер напрямую
$factory = new PasswordHasherFactory([
    'argon2id' => ['algorithm' => 'argon2id', 'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1],
]);
$hasher = $factory->getPasswordHasher('argon2id');

$isValid = $hasher->verify($user->getPassword(), $password);

if ($isValid) {
    echo "✓ Пароль '$password' ВАЛИДЕН для '{$user->getUsername()}'\n";
} else {
    echo "✗ Пароль '$password' НЕВЕРНЫЙ для '{$user->getUsername()}'\n";
}
