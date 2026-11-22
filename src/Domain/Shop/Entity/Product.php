<?php

declare(strict_types=1);

namespace App\Domain\Shop\Entity;

use App\Domain\Shop\ValueObject\Money;
use App\Domain\Shop\ValueObject\Slug;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Доменная сущность Продукта.
 */
#[ORM\Entity]
#[ORM\Table(name: 'product')]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products', fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    private ?Category $category = null;

    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $available = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $added;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $viewed = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastViewed = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $edited = null;

    private ?string $originalTitle = null;

    private ?string $originalDescription = null;

    public function __construct(
        string $title,
        string|int|float $price,
        string $description,
        Category $category
    ) {
        $this->title = $title;
        $this->slug = Slug::generate($title)->value();
        $this->price = $this->normalizePrice($price);
        $this->description = $description;
        $this->category = $category;
        $this->added = new \DateTimeImmutable();
        $this->images = new ArrayCollection();
        $this->rememberOriginalContent();
    }

    public function __toString(): string
    {
        return $this->getTitle();
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'price' => $this->price,
            'description' => $this->description,
            'available' => $this->available,
            'added' => $this->added,
            'viewed' => $this->viewed,
            'lastViewed' => $this->lastViewed,
            'edited' => $this->edited,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->slug = $data['slug'] ?? '';
        $this->price = $data['price'] ?? '0';
        $this->description = $data['description'] ?? '';
        $this->available = $data['available'] ?? 0;
        $this->added = $data['added'] ?? new \DateTimeImmutable();
        $this->viewed = $data['viewed'] ?? 0;
        $this->lastViewed = $data['lastViewed'] ?? null;
        $this->edited = $data['edited'] ?? null;
        $this->images = new ArrayCollection();
        $this->rememberOriginalContent();
    }

    #[ORM\PostLoad]
    public function rememberOriginalContent(): void
    {
        $this->originalTitle = $this->title;
        $this->originalDescription = $this->description;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $titleChanged = $this->originalTitle !== $this->title;
        $descriptionChanged = $this->originalDescription !== $this->description;

        if ($titleChanged || $descriptionChanged) {
            $this->edited = new \DateTimeImmutable();
        }

        $this->rememberOriginalContent();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string|int|float $price): self
    {
        $this->price = $this->normalizePrice($price);

        return $this;
    }

    public function getPriceVO(): Money
    {
        return Money::fromString($this->price);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getImages(): array
    {
        return $this->images->toArray();
    }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        $this->images->removeElement($image);
        if ($image->getProduct() === $this) {
            $image->setProduct(null);
        }

        return $this;
    }

    /**
     * @param iterable<ProductImage> $images
     */
    public function setImages(iterable $images): self
    {
        $newImages = [];
        foreach ($images as $image) {
            if ($image instanceof ProductImage) {
                $newImages[] = $image;
            }
        }

        foreach ($this->getImages() as $existingImage) {
            if (!\in_array($existingImage, $newImages, true)) {
                $this->removeImage($existingImage);
            }
        }

        foreach ($newImages as $image) {
            $this->addImage($image);
        }

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->available > 0;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }

    public function setAvailable(int $available): self
    {
        $this->available = $available;

        return $this;
    }

    public function getAdded(): \DateTimeImmutable
    {
        return $this->added;
    }

    public function getViewed(): int
    {
        return $this->viewed;
    }

    public function incrementViewed(): self
    {
        ++$this->viewed;
        $this->lastViewed = new \DateTimeImmutable();

        return $this;
    }

    public function getLastViewed(): ?\DateTimeImmutable
    {
        return $this->lastViewed;
    }

    public function getEdited(): ?\DateTimeImmutable
    {
        return $this->edited;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->slug = Slug::generate($title)->value();

        return $this;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = Slug::fromString($slug)->value();

        return $this;
    }

    private function normalizePrice(string|int|float $price): string
    {
        if (!is_numeric($price)) {
            throw new \InvalidArgumentException('Invalid product price');
        }

        return number_format((float) $price, 2, '.', '');
    }
}
