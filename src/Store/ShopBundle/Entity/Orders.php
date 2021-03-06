<?php
// src/ShopBundle/Entity/Orders.php

namespace Store\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="orders")
 */
class Orders
{
    //define constans for sold state
    const SOLD_PREPARED = false;
    const SOLD_DONE = true;
    
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @todo In template add ajaxed Country+city suggestion based on user input 
     * @ORM\Column(name="shipping", type="string", length=255)
     * @Assert\NotBlank(message="Please enter shipping address")
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
     * @Assert\Type(
     *     type="integer",
     *     message="The value {{ value }} is not a valid {{ type }}."
     * )
     * @Assert\NotBlank()
     * @Assert\GreaterThan(0)
     */
    protected $quantity;

    /**
     * @ORM\Column(name="sold", type="boolean", nullable=false)
     */
    protected $sold = Orders::SOLD_PREPARED;

    /**
     * @ORM\ManyToOne(targetEntity="Product", inversedBy="ord") 
     * @ORM\JoinColumn(name="product", referencedColumnName="id")
     */
    protected $product;

    /**
     * @ORM\ManyToOne(targetEntity="Customer", inversedBy="ord")
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
     * Constructor
     */
    public function __construct()
    {
        $this->edited = new \DateTime();
        $this->product = new \Doctrine\Common\Collections\ArrayCollection();
        $this->customer = new \Doctrine\Common\Collections\ArrayCollection();
        //$this->sold = Orders::SOLD_PREPARED; 
    }

//    public function __toString()
//    {
//        return $this->getId();
//    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * Set shipping
     *
     * @param string $shipping
     * @return Order
     */
    public function setShipping($shipping)
    {
        $this->shipping = $shipping;

        return $this;
    }

    /**
     * Get shipping
     *
     * @return string 
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * Set quantity
     *
     * @param integer $quantity
     * @return Order
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Get quantity
     *
     * @return integer 
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set sold
     *
     * @param tinyint $sold
     * @return Order
     */
    public function setSold($sold)
    {
        $this->sold = $sold;

        return $this;
    }

    /**
     * Get sold
     *
     * @return tinyint 
     */
    public function getSold()
    {
        return $this->sold;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Order
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime 
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set edited
     *
     * @param \DateTime $edited
     * @return Order
     */
    public function setEdited($edited)
    {
        $this->edited = $edited;

        return $this;
    }

    /**
     * Get edited
     *
     * @return \DateTime 
     */
    public function getEdited()
    {
        return $this->edited;
    }

    /**
     * Set product
     *
     * @param \Store\ShopBundle\Entity\Product $product
     * @return Order
     */
    public function setProduct(\Store\ShopBundle\Entity\Product $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product
     *
     * @return \Store\ShopBundle\Entity\Product 
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set customer
     *
     * @param \Store\ShopBundle\Entity\Customer $customer
     * @return Order
     */
    public function setCustomer(\Store\ShopBundle\Entity\Customer $customer = null)
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Get customer
     *
     * @return \Store\ShopBundle\Entity\Customer 
     */
    public function getCustomer()
    {
        return $this->customer;
    }
}
