<?php

declare(strict_types=1);

namespace App\Domain\Order\Entity;

use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Доменная сущность Заказа.
 */
#[ORM\Entity]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    public const STATUS_NEW = 'new';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const CHOICES = [
        'Новый' => self::STATUS_NEW, // когда тольк создан
        'Оплачен' => self::STATUS_PAID, // оплата получена
        'В обработке' => self::STATUS_PROCESSING, // в процессе обработки
        'Отправлен' => self::STATUS_SHIPPED, // оплачен и отправлен
        'Доставлен' => self::STATUS_DELIVERED, // клиенту доставили
        'Отменён' => self::STATUS_CANCELLED, // отменен(если была оплата, деньги возвращены)
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    private ?string $orderNumber = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $customerName;

    #[ORM\Column(type: Types::TEXT)]
    private string $deliveryAddress;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status = 'new';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Collection $items = null;

    public function __construct(
        string $customerName,
        string $deliveryAddress,
        ?User $user = null
    ) {
        $this->orderNumber = $this->generateOrderNumber();
        $this->customerName = $customerName;
        $this->deliveryAddress = $deliveryAddress;
        $this->user = $user;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->orderNumber ?? 'New Order';
    }

    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getDeliveryAddress(): string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(string $deliveryAddress): self
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): self
    {
        $this->additionalInfo = $additionalInfo;

        return $this;
    }

    public function getTotalAmount(): float
    {
        return (float) $this->totalAmount;
    }

    public function setTotalAmount(float $amount): self
    {
        $this->totalAmount = number_format($amount, 2, '.', '');

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function markAsPaid(): void
    {
        $this->paidAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PAID;
    }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function markAsShipped(): void
    {
        $this->shippedAt = new \DateTimeImmutable();
        $this->status = 'shipped';
    }

    public function getItems(): array
    {
        if ($this->items === null) {
            $this->items = new ArrayCollection();
        }

        return $this->items->toArray();
    }

    public function addItem(OrderItem $item): self
    {
        if ($this->items === null) {
            $this->items = new ArrayCollection();
        }

        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setOrder($this);
            $this->recalculateTotal();
        }

        return $this;
    }

    public function recalculateTotal(): void
    {
        if ($this->items === null) {
            $this->items = new ArrayCollection();
        }

        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getTotal();
        }
        $this->setTotalAmount($total);
    }

    public function removeItem(OrderItem $item): self
    {
        if ($this->items === null) {
            $this->items = new ArrayCollection();
        }

        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
            }
            $this->recalculateTotal();
        }

        return $this;
    }

    // human readable order.
    public function getHumanOrder(): string
    {
        return ($this->orderNumber)
            ? $this->orderNumber.' ('.$this->status.') '.$this->getTotalAmount()
            : 'New Order (new) 0.00'
        ;
    }

    #[ORM\PreUpdate]
    private function generateOrderNumber(): string
    {
        return 'ORD-'.strtoupper(uniqid());
    }
}
