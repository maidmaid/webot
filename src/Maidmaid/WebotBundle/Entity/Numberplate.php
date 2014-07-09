<?php

namespace Maidmaid\WebotBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Numberplate
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Maidmaid\WebotBundle\Entity\NumberplateRepository")
 */
class Numberplate
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="numberplate", type="integer")
     */
    private $numberplate;

    /**
     * @var string
     *
     * @ORM\Column(name="category", type="string", length=45, nullable=true)
     */
    private $category;

    /**
     * @var string
     *
     * @ORM\Column(name="subcategory", type="string", length=45, nullable=true)
     */
    private $subcategory;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=100, nullable=true)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="address", type="string", length=255, nullable=true)
     */
    private $address;

    /**
     * @var string
     *
     * @ORM\Column(name="complement", type="string", length=255, nullable=true)
     */
    private $complement;

    /**
     * @var string
     *
     * @ORM\Column(name="locality", type="string", length=45, nullable=true)
     */
    private $locality;

    /**
     * @var string
     *
     * @ORM\Column(name="info", type="string", length=100, nullable=true)
     */
    private $info;


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
     * Set numberplate
     *
     * @param integer $numberplate
     * @return Numberplate
     */
    public function setNumberplate($numberplate)
    {
        $this->numberplate = $numberplate;

        return $this;
    }

    /**
     * Get numberplate
     *
     * @return integer 
     */
    public function getNumberplate()
    {
        return $this->numberplate;
    }

    /**
     * Set category
     *
     * @param string $category
     * @return Numberplate
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return string 
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set subcategory
     *
     * @param string $subcategory
     * @return Numberplate
     */
    public function setSubcategory($subcategory)
    {
        $this->subcategory = $subcategory;

        return $this;
    }

    /**
     * Get subcategory
     *
     * @return string 
     */
    public function getSubcategory()
    {
        return $this->subcategory;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Numberplate
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set address
     *
     * @param string $address
     * @return Numberplate
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
     * Set complement
     *
     * @param string $complement
     * @return Numberplate
     */
    public function setComplement($complement)
    {
        $this->complement = $complement;

        return $this;
    }

    /**
     * Get complement
     *
     * @return string 
     */
    public function getComplement()
    {
        return $this->complement;
    }

    /**
     * Set locality
     *
     * @param string $locality
     * @return Numberplate
     */
    public function setLocality($locality)
    {
        $this->locality = $locality;

        return $this;
    }

    /**
     * Get locality
     *
     * @return string 
     */
    public function getLocality()
    {
        return $this->locality;
    }

    /**
     * Set info
     *
     * @param string $info
     * @return Numberplate
     */
    public function setInfo($info)
    {
        $this->info = $info;

        return $this;
    }

    /**
     * Get info
     *
     * @return string 
     */
    public function getInfo()
    {
        return $this->info;
    }
	
	public function toArray()
	{
		return get_object_vars($this);
	}
}
