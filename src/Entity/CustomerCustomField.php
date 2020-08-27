<?php
namespace PrestaShop\Customerfield\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */
class CustomerCustomField // table ps_customer_custom_field
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_customer_custom_field", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_customer", type="integer")
     */
    private $customerId;

    /**
     * @var string
     *
     * @ORM\Column(name="shipping_preferences", type="text")
     */
    private $shipping_preferences;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    /**
     * @param int $customerId
     */
    public function setCustomerId(int $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return string
     */
    public function getShippingPreferences(): string
    {
        return $this->shipping_preferences;
    }

    /**
     * @param string $shipping_preferences
     */
    public function setShippingPreferences(string $shipping_preferences): void
    {
        $this->shipping_preferences = $shipping_preferences;
    }
}
