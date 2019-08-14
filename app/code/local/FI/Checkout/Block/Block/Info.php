<?php
/**
 * Zero Step Checkout user information block
 *
 * @category    FI
 * @package     FI_Checkout
 * @author      Sergiy Stotskiy <sergiy.stotskiy@freaksidea.com>
 */
class FI_Checkout_Block_Block_Info extends Mage_Checkout_Block_Onepage_Shipping
{
    /**
     * Information about user stored in session
     *
     * @var Varien_Object
     */
    protected $_user;

    /**
     * Constructor. Set block template.
     * Set user values to block. Update addresses
     */
    protected function _construct()
    {
        $this->setTemplate('freaks/checkout/block/info.phtml');

        $user = $this->getCustomerInfo();
        $streetIndex = 2;

        if ($this->isCustomerLoggedIn()) {
            $customer   = $this->getCustomer();
            $address    = $this->getAddress();
            $additional = $user->getAddress();

            if (is_array($additional) && !empty($additional)) {
                $address->addData(array_filter($additional));
            }
            $customer->setNote($user->getNote());
        } else {
            $customer = $user;
            $address  = new Varien_Object($customer->getAddress());
            $streetIndex--;
            $this->helper('fi_checkout')->updateAddress($address);
        }

        $this->setCustomerEmail($customer->getEmail())
            ->setCustomerRegion($address->getRegion())
            ->setCustomerCity($address->getCity())
            ->setCustomerBuilding($address->getStreet($streetIndex))
            ->setCustomerRoom($address->getStreet($streetIndex + 1))
            ->setCustomerNote($customer->getNote())
            ->setCustomerPhone($address->getTelephone())
            ->setCustomerZip($address->getPostcode());

        $this->_updateAddress();
    }

    /**
     * Return customer full name
     *
     * @return string
     */
    public function getCustomerName()
    {
        return $this->isCustomerLoggedIn()
            ? $this->getFirstname() . ' ' . $this->getLastname()
            : $this->getCustomerInfo()->getName();
    }

    /**
     * Return Customer Address First Name
     * If Sales Quote Address First Name is not defined - return Customer First Name
     *
     * @return string
     */
    public function getFirstname()
    {
        $firstname = $this->getAddress()->getFirstname();
        if (empty($firstname) && $this->getQuote()->getCustomer()) {
            return $this->getQuote()->getCustomer()->getFirstname();
        }
        return $firstname;
    }

    /**
     * Return Customer Address Last Name
     * If Sales Quote Address Last Name is not defined - return Customer Last Name
     *
     * @return string
     */
    public function getLastname()
    {
        $lastname = $this->getAddress()->getLastname();
        if (empty($lastname) && $this->getQuote()->getCustomer()) {
            return $this->getQuote()->getCustomer()->getLastname();
        }
        return $lastname;
    }

    /**
     * Return address street
     *
     * @return string
     */
    public function getStreet()
    {
        if ($this->isCustomerLoggedIn()) {
            $street1 = $this->getAddress()->getStreet(1);
        } else {
            $street1 = '';
            $address = $this->getCustomerInfo()->getAddress();
            if (!empty($address['street']) && is_array($address['street'])) {
                $street1 = reset($address['street']);
            }
        }

        return $street1;
    }

    /**
     * Return address id
     *
     * @return int
     */
    public function getAddressId()
    {
        $addressId = $this->getAddress()->getCustomerAddressId();
        if (empty($addressId)) {
            $address = $this->getCustomer()->getPrimaryShippingAddress();
            if ($address) {
                $addressId = $address->getId();
            }
        }
        return $addressId;
    }

    /**
     * Return customer information stored in session
     *
     * @return Varien_Object
     */
    public function getCustomerInfo()
    {
        if ($this->_user) {
            return $this->_user;
        }
        $data = Mage::getSingleton('customer/session')->getCheckoutUser();

        $this->_user = new Varien_Object($data);
        return $this->_user;
    }

    /**
     * Return address. If address object is empty import customer address into it
     *
     * @return Mage_Sales_Quote_Address
     */
    public function getAddress()
    {
        $address = parent::getAddress();
        if ($this->isCustomerLoggedIn() && !$address->getEmail()) {
            $customerAddress = $this->getCustomer()->getPrimaryShippingAddress();
            if (is_object($customerAddress)) {
                $address->importCustomerAddress($customerAddress)
                    ->setSaveInAddressBook(0);
            }
        }

        return $address;
    }

    /**
     * Return customer location (country, region, city)
     *
     * @return string
     */
    public function getCustomerLocation()
    {
        $address = $this->getCustomerInfo()->getAddress();
        if (!empty($address['location'])) {
            return $address['location'];
        }

        $address = $this->getAddress();

        if (!$this->helper('fi_checkout')->useOnlyDefaultCountry()) {
            $location[] = $address->getCountryModel()->getName();
        }
        if ($address->getRegionId()) {
            $location[] = $address->getRegionModel()->getName();
        } elseif ($address->getRegion()) {
            $location[] = $address->getRegion();
        }
        $location[] = $address->getCity();

        return join(', ', array_filter($location));
    }

    /**
     * Return location help message based on configuration
     *
     * @see FI_Checkout_Helper_Data::useOnlyDefaultCountry
     * @return string
     */
    public function getLocationHelp()
    {
        if ($this->helper('fi_checkout')->useOnlyDefaultCountry()) {
            return $this->__('City');
        } else {
            return $this->__('Country, Region, City (e.g. Ukraine, Kyiv, Kyiv)');
        }
    }

    /**
     * Update addresses with session values
     *
     * @return FI_Checkout_Block_Block_Info
     */
    protected function _updateAddress()
    {
        $shipping = $this->helper('fi_checkout')
            ->updateAddress($this->getAddress())
            ->implodeStreetAddress();

        $billing  = $this->getQuote()->getBillingAddress();
        if ($billing) {
            $this->helper('fi_checkout')
                ->copyAddress($shipping, $billing)
                ->implodeStreetAddress();
        }
        return $this;
    }

    /**
     * Return country select box block
     *
     * @param string $type
     * @return Mage_Core_Block_Html_Select
     */
    public function getCountryBlock($type = 'shipping')
    {
        $countryId = $this->getAddress()->getCountryId();
        if (!$countryId) {
            $countryId = Mage::helper('fi_checkout')->getDefaultCountry();
        }
        $select = $this->getLayout()->createBlock('core/html_select')
            ->setTitle($this->helper('checkout')->__('Country'))
            ->setClass('validate-select')
            ->setValue($countryId)
            ->setOptions($this->getCountryOptions());

        return $select;
    }

    /**
     * Return region select box block
     *
     * @param string $type
     * @return Mage_Core_Block_Html_Select
     */
    public function getRegionBlock()
    {
        $select = $this->getLayout()->createBlock('core/html_select')
            ->setTitle($this->helper('checkout')->__('State/Province'))
            ->setClass('required-entry validate-state')
            ->setValue($this->getAddress()->getRegionId())
            ->setOptions($this->getRegionCollection()->toOptionArray());

        return $select;
    }
}
