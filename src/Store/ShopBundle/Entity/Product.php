<?php

declare(strict_types=1);
// src/Store/ShopBundle/Entity/Product.php

namespace App\Store\ShopBundle\Entity;

use Cocur\Slugify\Slugify;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass="App\Store\ShopBundle\Repository\ProductRepository")
 *
 * @ORM\Table(name="product")
 */
class Product
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
     *
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
     * Constructor.
     */
    public function __construct()
    {
        $this->category = new ArrayCollection();
        $this->image = new ArrayCollection();
        $this->ord = new ArrayCollection();
        $this->added = new \DateTime();
    }

    public function __toString()
    {
        return $this->getTitle();
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
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
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set slug.
     *
     * @param string $slug
     *
     * @return Product
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get price.
     *
     * @return string
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set price.
     *
     * @param string $price
     *
     * @return Product
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return Product
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get available.
     *
     * @return int
     */
    public function getAvailable()
    {
        return $this->available;
    }

    /**
     * Set available.
     *
     * @param int $available
     *
     * @return Product
     */
    public function setAvailable($available)
    {
        $this->available = $available;

        return $this;
    }

    /**
     * Get added.
     *
     * @return \DateTime
     */
    public function getAdded()
    {
        return $this->added;
    }

    /**
     * Set added.
     *
     * @param \DateTime $added
     *
     * @return Product
     */
    public function setAdded($added)
    {
        $this->added = $added;

        return $this;
    }

    /**
     * Get viewed.
     *
     * @return int
     */
    public function getViewed()
    {
        return $this->viewed;
    }

    /**
     * Set viewed.
     *
     * @param int $viewed
     *
     * @return Product
     */
    public function setViewed($viewed)
    {
        $this->viewed = $viewed;

        return $this;
    }

    /**
     * Get lastViewed.
     *
     * @return \DateTime
     */
    public function getLastViewed()
    {
        return $this->lastViewed;
    }

    /**
     * Set lastViewed.
     *
     * @param \DateTime $lastViewed
     *
     * @return Product
     */
    public function setLastViewed($lastViewed)
    {
        $this->lastViewed = $lastViewed;

        return $this;
    }

    /**
     * Get category.
     *
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set category.
     *
     * @return Product
     */
    public function setCategory(?Category $category = null)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Add image.
     *
     * @return Product
     */
    public function addImage(Productimage $image)
    {
        $this->image[] = $image;

        return $this;
    }

    /**
     * Remove image.
     */
    public function removeImage(Productimage $image): void
    {
        $this->image->removeElement($image);
    }

    /**
     * Get image.
     *
     * @return Collection
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Add ord.
     *
     * @return Product
     */
    public function addOrd(Orders $ord)
    {
        $this->ord[] = $ord;

        return $this;
    }

    /**
     * Remove ord.
     */
    public function removeOrd(Orders $ord): void
    {
        $this->ord->removeElement($ord);
    }

    /**
     * Get ord.
     *
     * @return Collection
     */
    public function getOrd()
    {
        return $this->ord;
    }
}
