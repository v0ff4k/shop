<?php

declare(strict_types=1);

namespace App\Domain\Shop\ValueObject;

/**
 * ValueObject для URL-дружественного слага.
 */
final class Slug
{
    private function __construct(
        private readonly string $value
    ) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException('Invalid slug format');
        }

        if (\strlen($value) > 255) {
            throw new \InvalidArgumentException('Slug must not exceed 255 characters');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function generate(string $title): self
    {
        $source = trim($title);

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($transliterator !== null) {
                $source = $transliterator->transliterate($source);
            }
        }

        $slug = mb_strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-]+/', '-', $source)), '-'));

        if ($slug === '') {
            $slug = 'item-'.substr(sha1($title), 0, 12);
        }

        return new self($slug);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
