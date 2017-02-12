<?php
// src/Store/ShopBundle/Entity/Product.php
namespace Store\ShopBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Cocur\Slugify\Slugify;

/**
 * @ORM\Entity(repositoryClass="Store\ShopBundle\Repository\ProductRepository")
 * @ORM\Table(name="product")
 */
class Product
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=128)
     */
    protected $title;


    /**
     * @ORM\Column(type="string", length=128, unique=true)
     */
    protected $slug;    
    
    
    /**
     * @ORM\Column(type="decimal", scale=2)
     */
    protected $price;

    /**
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="products")
     * @ORM\JoinColumn(name="сategory", referencedColumnName="id")
     */
    protected $category;
   
    /**
     * @ORM\OneToMany(targetEntity="Productimage", mappedBy="product")
     */
    protected $image;
    
    /**
     * @ORM\OneToMany(targetEntity="Orders", mappedBy="product")
     */
    protected $ord;
    
    /**
     * @ORM\Column(name="available", type="integer")
     */
    protected $available;

    /**
     * @ORM\Column(name="added", type="datetime", nullable=false)
     */
    protected $added;

    /**
     * @ORM\Column(name="viewed", type="integer")
     */
    protected $viewed;

    /**
     * @ORM\Column(name="lastViewed", type="datetime", nullable=false)
     */
    protected $lastViewed;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->category = new \Doctrine\Common\Collections\ArrayCollection();
        $this->image = new \Doctrine\Common\Collections\ArrayCollection();
        $this->ord = new \Doctrine\Common\Collections\ArrayCollection();
        $this->added = new \DateTime();
    }

    public function __toString()
    {
        return $this->getTitle();
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
     * Set title
     *
     * @param string $title
     * @return Product
     */
    public function setTitle($title)
    {
        $slugify = new Slugify();
        $this->title = $title;
        $this->slug = $slugify->slugify($title);

        return $this;
    }

    /**
     * Get title
     *
     * @return string 
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set slug
     *
     * @param string $slug
     * @return Product
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
        
        return $this;
    }

    /**
     * Get slug
     *
     * @return string 
     */
    public function getSlug()
    {
        return $this->slug;
    }
    
    /**
     * Set price
     *
     * @param string $price
     * @return Product
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get price
     *
     * @return string 
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return Product
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set available
     *
     * @param integer $available
     * @return Product
     */
    public function setAvailable($available)
    {
        $this->available = $available;

        return $this;
    }

    /**
     * Get available
     *
     * @return integer 
     */
    public function getAvailable()
    {
        return $this->available;
    }

    /**
     * Set added
     *
     * @param \DateTime $added
     * @return Product
     */
    public function setAdded($added)
    {
        $this->added = $added;

        return $this;
    }

    /**
     * Get added
     *
     * @return \DateTime 
     */
    public function getAdded()
    {
        return $this->added;
    }

    /**
     * Set viewed
     *
     * @param integer $viewed
     * @return Product
     */
    public function setViewed($viewed)
    {
        $this->viewed = $viewed;

        return $this;
    }

    /**
     * Get viewed
     *
     * @return integer 
     */
    public function getViewed()
    {
        return $this->viewed;
    }

    /**
     * Set lastViewed
     *
     * @param \DateTime $lastViewed
     * @return Product
     */
    public function setLastViewed($lastViewed)
    {
        $this->lastViewed = $lastViewed;

        return $this;
    }

    /**
     * Get lastViewed
     *
     * @return \DateTime 
     */
    public function getLastViewed()
    {
        return $this->lastViewed;
    }

    /**
     * Set category
     *
     * @param \Store\ShopBundle\Entity\Category $category
     * @return Product
     */
    public function setCategory(\Store\ShopBundle\Entity\Category $category = null)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return \Store\ShopBundle\Entity\Category 
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Add image
     *
     * @param \Store\ShopBundle\Entity\Productimage $image
     * @return Product
     */
    public function addImage(\Store\ShopBundle\Entity\Productimage $image)
    {
        $this->image[] = $image;

        return $this;
    }

    /**
     * Remove image
     *
     * @param \Store\ShopBundle\Entity\Productimage $image
     */
    public function removeImage(\Store\ShopBundle\Entity\Productimage $image)
    {
        $this->image->removeElement($image);
    }

    /**
     * Get image
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Add ord
     *
     * @param \Store\ShopBundle\Entity\Orders $ord
     * @return Product
     */
    public function addOrd(\Store\ShopBundle\Entity\Orders $ord)
    {
        $this->ord[] = $ord;

        return $this;
    }

    /**
     * Remove ord
     *
     * @param \Store\ShopBundle\Entity\Orders $ord
     */
    public function removeOrd(\Store\ShopBundle\Entity\Orders $ord)
    {
        $this->ord->removeElement($ord);
    }

    /**
     * Get ord
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getOrd()
    {
        return $this->ord;
    }
}
