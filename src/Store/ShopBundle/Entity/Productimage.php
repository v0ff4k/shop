<?php
// src/Store/ShopBundle/Entity/Productimage.php
namespace Store\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="productimage")
 */
class Productimage
{

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Product", inversedBy="image")
     * @ORM\JoinColumn(name="product", referencedColumnName="id")
     */
    protected $product;

    /**
     * @ORM\Column(name="imagename", type="string", length=128)
     */
    protected $imagename;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->imagename = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __toString()
    {
        return $this->getImagename();
    }

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
     * Set imagename
     *
     * @param string $imagename
     * @return Productimage
     */
    public function setImagename($imagename)
    {
        $this->imagename = $imagename;

        return $this;
    }

    /**
     * Get imagename
     *
     * @return string 
     */
    public function getImagename()
    {
        return $this->imagename;
    }

    /**
     * Set product
     *
     * @param \Store\ShopBundle\Entity\Product $product
     * @return Productimage
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
}
