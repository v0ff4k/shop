<?php

declare(strict_types=1);

namespace App\Domain\Shop\Entity;

use App\Domain\Shop\ValueObject\Slug;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Доменная сущность Категории.
 */
#[ORM\Entity]
#[ORM\Table(name: 'category')]
#[ORM\HasLifecycleCallbacks]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $added;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $edited = null;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category', orphanRemoval: true, cascade: ['persist'])]
    private Collection $products;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children', fetch: 'LAZY')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $children;

    public function __construct(string $title = '', string $slug = '')
    {
        $this->products = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->added = new \DateTimeImmutable();

        if ($title && $slug) {
            $this->initTitleAndSlug($title, $slug);
        }
        if (!$title && $slug) {
            // no title, or mb forgotten
            $this->setSlug($slug)->setTitle($slug);
        }
        if ($title && !$slug) {
            // меняется slug только если он не был указан
            $this->genFromTitle($title)->setTitle($title);
        }
    }

    // Если будет дубляж, БД не пропустит, и выдаст ошибку

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
            'image' => $this->image,
            'added' => $this->added,
            'edited' => $this->edited,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->slug = $data['slug'] ?? '';
        $this->image = $data['image'] ?? null;
        $this->added = $data['added'] ?? new \DateTimeImmutable();
        $this->edited = $data['edited'] ?? null;
        // Коллекции создаются в конструкторе или Doctrine }
        // При десериализации объект "оживает" из сериализованного состояния.
        // Doctrine сам управляет коллекциями через прокси-объекты  !
        // $this->products = new ArrayCollection();
        // $this->children = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->edited = new \DateTimeImmutable();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = Slug::fromString($slug)->value();

        return $this;
    }

    public function genFromTitle($title)
    {
        $this->slug = Slug::generate($title)->value();

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    // alias for image getter
    public function getImageSrc(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getAdded(): \DateTimeImmutable
    {
        return $this->added;
    }

    // keept for sonata admin, as fake setters.
    public function setAdded(?\DateTimeInterface $added): self
    {
        // DateTimeImmutable::createFromFormat($format, $string);
        $this->added = $added ?? new \DateTimeImmutable();

        return $this;
    }

    public function getEdited(): ?\DateTimeImmutable
    {
        return $this->edited;
    }

    public function getProducts(): array
    {
        return $this->products->toArray();
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setCategory($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        if ($this->products->removeElement($product)) {
            if ($product->getCategory() === $this) {
                $product->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @param Collection<int, Category> $children
     */
    public function setChildren(Collection $children): self
    {
        // return $this->addChildren($children);
        $this->children = $children;

        return $this;
    }

    /**
     * Добавляет дочернюю категорию с проверкой циклов.
     */
    public function addChild(self $child): self
    {
        if ($child === $this) {
            throw new \LogicException('Категория не может быть дочерней самой себе');
        }

        // Проверяем цепочку родителей по идентичности объектов, чтобы
        // корректно работать и с ещё не сохранёнными категориями.
        $ancestor = $this->getParent();
        while ($ancestor !== null) {
            if ($ancestor === $child) {
                throw new \LogicException(\sprintf('Категория "%s" не может быть дочерней для "%s" (создаст цикл)', $child->getTitle(), $this->getTitle()));
            }
            $ancestor = $ancestor->getParent();
        }

        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this); // Для двусторонней связи
        }

        return $this;
    }

    /**
     * Удаляет дочернюю категорию.
     */
    public function removeChild(self $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            $child->setParent(null);
        }

        return $this;
    }

    /**
     * Добавляет одну или несколько дочерних категорий.
     *
     * @todo OUTDATED ?
     *
     * @param array ...$children
     *
     * @throws \LogicException Если обнаружен цикл в иерархии
     */
    public function addChildren(self ...$children): self
    {
        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Устанавливает родителя с проверкой цикла.
     *
     * @throws \LogicException
     */
    public function setParent(?self $parent): self
    {
        if ($parent !== null) {
            if ($parent === $this) {
                throw new \LogicException('Категория не может быть самой собой родителем');
            }

            // Проверяем цепочку родителей по идентичности объектов: это
            // работает и до сохранения категорий в БД.
            $ancestor = $parent;
            while ($ancestor !== null) {
                if ($ancestor === $this) {
                    throw new \LogicException(\sprintf('Категория "%s" не может быть родительской, так как является дочерней', $parent->getTitle()));
                }
                $ancestor = $ancestor->getParent();
            }
        }

        $this->parent = $parent;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function initTitleAndSlug(string $title, string $slug): self
    {
        $this->setTitle($title)->setSlug($slug);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Проверяет, является ли текущая категория предком для указанной.
     *
     * Н-р: После добавления    $parent->addChildren($child1, $child2);
     * $parent->isAncestorOf($child1)  вернет  true
     */
    public function isAncestorOf(self $childCategory): bool
    {
        $ancestor = $childCategory->getParent();
        while ($ancestor !== null) {
            if ($ancestor === $this) {
                return true;
            }
            $ancestor = $ancestor->getParent();
        }

        return false;
    }

    /**
     * Возвращает полный путь категории (для URL или отображения)
     * $parent->getPath(); // "Родительская"
     * $child1->getPath(); // "Родительская / Дочерняя 1".
     *
     * @param string $separator * @return string
     */
    public function getPath(string $separator = ' / '): string
    {
        $path = [];
        $current = $this;

        while ($current !== null) {
            array_unshift($path, $current->getTitle());
            $current = $current->getParent();
        }

        return implode($separator, $path);
    }

    // __wakeup() НЕ нужен, так как Doctrine управляет жизненным циклом коллекций.
}
