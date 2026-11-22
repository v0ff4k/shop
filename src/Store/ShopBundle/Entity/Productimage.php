<?php

declare(strict_types=1);
// src/Store/ShopBundle/Entity/Productimage.php

namespace App\Store\ShopBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="productimage")
 */
class Productimage
{
    /**
     * @ORM\Column(type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Product", inversedBy="image")
     *
     * @ORM\JoinColumn(name="product", referencedColumnName="id")
     */
    protected $product;

    /**
     * @ORM\Column(name="imagename", type="string", length=128)
     */
    protected $imagename;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->imagename = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getImagename();
    }

    /**
     * Get imagename.
     *
     * @return string
     */
    public function getImagename()
    {
        return $this->imagename;
    }

    /**
     * Set imagename.
     *
     * @param string $imagename
     *
     * @return Productimage
     */
    public function setImagename($imagename)
    {
        $this->imagename = $imagename;

        return $this;
    }

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
     * @return Productimage
     */
    public function setProduct(?Product $product = null)
    {
        $this->product = $product;

        return $this;
    }
}
