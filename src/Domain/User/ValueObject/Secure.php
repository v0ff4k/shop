<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use App\Domain\User\Entity\User;

/**
 * ValueObject для email адреса.
 */
final class Secure
{
    private readonly string $field;
    private readonly array $availableRoles;

    private readonly array $allowedFields;

    private readonly array $roles;

    private function __construct(string $field, array $roles)
    {
        $normalized = mb_strtolower($field);
        $this->field = $normalized;

        $this->availableRoles = array_values(User::CHOICES);

        $this->allowedFields = ['id', 'username', 'passwordHash', 'email', 'fullName', 'address',
            'additionalInfo', 'roles', 'enabled', 'registeredAt', 'lastLoginAt', 'updatedAt', 'orders'];

        // TODO normalize(on  m-y dim-l array)
        $this->roles = array_values($roles);
    }

    /**
     * Rules per field and auth user role.
     */
    public static function isFieldAndRole(string $field, array $roles): bool
    {
        $seq = new self($field, $roles);

        if (!$seq->isEmptyFieldOrRoles()) {
            return false;
        }

        return !($seq->isnotInFields() || $seq->isInRoles())

        ;
    }

    private function isnotInFields()
    {
        return !\in_array($this->field, $this->allowedFields, true);
    }

    private function isInRoles(): bool
    {
        return !empty(array_intersect($this->availableRoles, $this->roles));
    }

    private function isEmptyFieldOrRoles(): bool
    {
        return empty($this->field) || empty($this->roles);
    }
}
