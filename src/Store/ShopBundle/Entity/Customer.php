<?php
// src/Store/ShopBundle/Entity/Customer.php
namespace Store\ShopBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="customer")
 */
class Customer extends BaseUser
{

    //define constans for roles state, default in FOSUserBundle
    const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    const ROLE_ADMIN = 'ROLE_ADMIN';
    const ROLE_USER = 'ROLE_USER';
    const ROLE_SONATA_ADMIN = 'ROLE_SONATA_ADMIN';
    const ROLE_ALLOWED_TO_SWITCH = 'ROLE_ALLOWED_TO_SWITCH';
    
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="fullname", type="string", length=255)
     * @Assert\Length(
     *      min = 3,
     *      max = 50,
     *      minMessage = "Your full name must be at least {{ limit }} characters long",
     *      maxMessage = "Your full name cannot be longer than {{ limit }} characters"
     * )
     */
    protected $fullname;

    /**
     * @ORM\Column(name="address", type="string", length=255)
     * @Assert\NotBlank(message="Please enter shipping address")
     * @Assert\Length(
     *     min=20,
     *     max=255,
     *     minMessage="The address is too short.",
     *     maxMessage="The address is too long."
     * )
     */
    protected $address;

    /**
     * @ORM\OneToMany(targetEntity="Orders", mappedBy="customer")
     */
    protected $ord;

    public function __construct()
    {
        parent::__construct();
        // your own logic
//        from original      
//        $this->salt = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
//        $this->enabled = false;
//        $this->locked = false;
//        $this->expired = false;
//        $this->roles = array();
//        $this->credentialsExpired = false;
        $this->ord = new \Doctrine\Common\Collections\ArrayCollection();
    }

//    public function __toString()
//    {
//        //return (string) $this->getFullname();//mine
//        //return (string) $this->getUsername();//parent
//        parent::__toString();
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
     * Set fullname
     *
     * @param string $fullname
     * @return Customer
     */
    public function setFullname($fullname)
    {
        $this->fullname = $fullname;

        return $this;
    }

    /**
     * Get fullname
     *
     * @return string 
     */
    public function getFullname()
    {
        return $this->fullname;
    }

    /**
     * Set address
     *
     * @param string $address
     * @return Customer
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get address
     *
     * @return string 
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Add ord
     *
     * @param \Store\ShopBundle\Entity\Orders $ord
     * @return Customer
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
