<?php

declare(strict_types=1);
// src/ShopBundle/Entity/Orders.php

namespace App\Store\ShopBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="orders")
 */
class Orders
{
    // define constans for sold state
    public const SOLD_PREPARED = false;
    public const SOLD_DONE = true;

    /**
     * @ORM\Column(type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @todo In template add ajaxed Country+city suggestion based on user input
     *
     * @ORM\Column(name="shipping", type="string", length=255)
     *
     * @Assert\NotBlank(message="Please enter shipping address")
     *
     * @Assert\Length(
     *     min=20,
     *     max=255,
     *     minMessage="The address is too short.",
     *     maxMessage="The address is too long."
     * )
     */
    protected $shipping;

    /**
     * @ORM\Column(name="quantity", type="integer")
     *
     * @Assert\Type(
     *     type="integer",
     *     message="The value {{ value }} is not a valid {{ type }}."
     * )
     *
     * @Assert\NotBlank()
     *
     * @Assert\GreaterThan(0)
     */
    protected $quantity;

    /**
     * @ORM\Column(name="sold", type="boolean", nullable=false)
     */
    protected $sold = self::SOLD_PREPARED;

    /**
     * @ORM\ManyToOne(targetEntity="Product", inversedBy="ord")
     *
     * @ORM\JoinColumn(name="product", referencedColumnName="id")
     */
    protected $product;

    /**
     * @ORM\ManyToOne(targetEntity="Customer", inversedBy="ord")
     *
     * @ORM\JoinColumn(name="customer", referencedColumnName="id")
     */
    protected $customer;

    /**
     * @ORM\Column(name="created", type="datetime", nullable=false)
     */
    protected $created;

    /**
     * @ORM\Column(name="edited", type="datetime", name="edited", nullable=false)
     */
    protected $edited;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->edited = new \DateTime();
        $this->product = new ArrayCollection();
        $this->customer = new ArrayCollection();
        // $this->sold = Orders::SOLD_PREPARED;
    }

    //    public function __toString()
    //    {
    //        return $this->getId();
    //    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get shipping.
     *
     * @return string
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * Set shipping.
     *
     * @param string $shipping
     *
     * @return Order
     */
    public function setShipping($shipping)
    {
        $this->shipping = $shipping;

        return $this;
    }

    /**
     * Get quantity.
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set quantity.
     *
     * @param int $quantity
     *
     * @return Order
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Get sold.
     *
     * @return tinyint
     */
    public function getSold()
    {
        return $this->sold;
    }

    /**
     * Set sold.
     *
     * @param tinyint $sold
     *
     * @return Order
     */
    public function setSold($sold)
    {
        $this->sold = $sold;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return Order
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get edited.
     *
     * @return \DateTime
     */
    public function getEdited()
    {
        return $this->edited;
    }

    /**
     * Set edited.
     *
     * @param \DateTime $edited
     *
     * @return Order
     */
    public function setEdited($edited)
    {
        $this->edited = $edited;

        return $this;
    }

    /**
     * Get product.
     *
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set product.
     *
     * @return Order
     */
    public function setProduct(?Product $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get customer.
     *
     * @return Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * Set customer.
     *
     * @return Order
     */
    public function setCustomer(?Customer $customer = null)
    {
        $this->customer = $customer;

        return $this;
    }
}
