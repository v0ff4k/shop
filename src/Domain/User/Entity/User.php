<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Order\Entity\Order;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Password;
use App\Domain\User\ValueObject\UserId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Доменная сущность Пользователя.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_CUSTOMER = 'ROLE_CUSTOMER';
    public const ROLE_SELLER = 'ROLE_SELLER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_STAFF = 'ROLE_STAFF';
    public const ROLE_USER = 'ROLE_USER';

    public const CHOICES = [
        'Администратор' => self::ROLE_ADMIN,
        'Продавец' => self::ROLE_SELLER,
        'Покупатель' => self::ROLE_CUSTOMER,
        'Сотрудник' => self::ROLE_STAFF,
        'Пользователь' => self::ROLE_USER,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    private string $username;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $passwordHash;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private \Doctrine\Common\Collections\Collection $orders;

    // Временное поле для формы (не сохраняется в БД)
    private ?string $plainPassword = null;

    public function __construct(
        string $username,
        string $email,
        string $plainPassword,
        array $roles = []
    ) {
        $this->username = $username;
        $this->email = Email::fromString($email)->value();
        $passwordVO = Password::fromPlain($plainPassword);
        $this->passwordHash = $passwordVO->hash();
        $this->roles = $roles ?: [self::ROLE_CUSTOMER];
        $this->registeredAt = new \DateTimeImmutable();
        $this->orders = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->username;
    }

    #[ORM\PrePersist]
    public function ensureRegisteredAt(): void
    {
        // If for some reason it was left uninitialized or null, set it now
        if (!isset($this->registeredAt)) {
            $this->registeredAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return UserId::fromInt($this->id);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        $this->touchUpdatedAt();

        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPassword(string $plainPassword): self
    {
        $passwordVO = Password::fromPlain($plainPassword);
        $this->passwordHash = $passwordVO->hash();
        $this->touchUpdatedAt();

        return $this;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = Email::fromString($email)->value();
        $this->touchUpdatedAt();

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;
        $this->touchUpdatedAt();

        return $this;
    }

    public function getFullNameAndEmail()
    {
        return $this->fullName.' ('.$this->email.')';
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        $this->touchUpdatedAt();

        return $this;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): self
    {
        $this->additionalInfo = $additionalInfo;
        $this->touchUpdatedAt();

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        $this->touchUpdatedAt();

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    // TODO test !
    public function getHumanRoles(): string
    {
        $choices = self::CHOICES;
        $displayRoles = [];

        foreach ($this->roles as $role) {
            if (isset($choices[$role])) {
                $displayRoles[] = $choices[$role];
            } else {
                $displayRoles[] = $role; // fallback для неизвестных ролей
            }
        }

        return implode(', ', $displayRoles);
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getSalt(): ?string
    {
        return null;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function addRole(string $role): self
    {
        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
            $this->touchUpdatedAt();
        }

        return $this;
    }

    public function removeRole(string $role): self
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
            $this->touchUpdatedAt();
        }

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->getRoles(), true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->touchUpdatedAt();

        return $this;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(\DateTimeImmutable $registeredAt): self
    {
        $this->registeredAt = $registeredAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function markAsLoggedIn(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getOrders(): array
    {
        return $this->orders->toArray();
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * Обновляет updatedAt только при изменении данных пользователя
     * (не вызывается при изменении last_login_at).
     */
    private function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
