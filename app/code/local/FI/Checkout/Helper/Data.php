<?php
/**
 * Zero Step Checkout helper
 *
 * @category    FI
 * @package     FI_Checkout
 * @author      Sergiy Stotskiy <sergiy.stotskiy@freaksidea.com>
 * @copyright   Copyright (c) 2012 Sergiy Stotskiy (http://freaksidea.com)
 */
class FI_Checkout_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Xml configuration pathes
     */
    const XML_PATH_DEFAULT_COUNTRY = 'checkout/easyco/default_country';
    const XML_PATH_DEFAULT_REGION  = 'checkout/easyco/default_region';
    const XML_PATH_DEFAULT_CITY    = 'checkout/easyco/default_city';
    const XML_PATH_ALLOW_GUEST_CO  = 'checkout/easyco/allow_guest_checkout';
    const XML_PATH_USE_DEFAULT_COUNTRY = 'checkout/easyco/use_default_country';
    const XML_PATH_PASSWORD_FIELD_TYPE = 'checkout/easyco/password_type';
    const XML_PATH_LOCATION_FIELD_TYPE = 'checkout/easyco/location_type';
    const XML_PATH_SHOW_BUILD_FIELDS   = 'checkout/easyco/show_build_fields';
    const XML_PATH_MAY_SUBSCRIBE_GUEST ='newsletter/subscription/allow_guest_subscribe';
    const XML_PATH_SHOW_NEWSLETTER     = 'checkout/easyco/show_newsletter';
    const XML_PATH_NEWSLETTER_SEND_SUCCESS_EMAIL = 'checkout/easyco/newsletter_send_success';
    const XML_PATH_NEWSLETTER_SEND_REQUEST_EMAIL = 'checkout/easyco/newsletter_send_request';

    protected
        /**
         * List of specific address fields
         *
         * @var array
         */
        $_copyAddressFields = array('country_id', 'region', 'region_id', 'city', 'postcode');

    /**
     * Return attribute options
     *
     * @param string $entityType
     * @param string $attrCode
     * @param bool   $withEmpty
     * @return array
     */
    public function getAttributeOptions($entityType, $attrCode, $withEmpty = false)
    {
        $data = Mage::getModel('eav/entity_attribute')
            ->loadByCode($entityType, $attrCode)
            ->setSourceModel('eav/entity_attribute_source_table')
            ->getSource()
            ->getAllOptions($withEmpty);

        $set = array();
        foreach ($data as &$row) {
            $set[$row['value']] = $row['label'];
        }

        return $set;
    }

    /**
     * Return default shipping address
     *
     * @return array
     */
    public function getDefaultShippingAddress()
    {
        return array(
            'country_id' => $this->getDefaultCountry(),
            'region'     => $this->getDefaultRegion(),
            'region_id'  => null,
            'city'       => $this->getDefaultCity()
        );
    }

    /**
     * Lower case text using mb_string
     *
     * @param string $text
     * @return string
     */
    public function lowerCase($text)
    {
        if (function_exists('mb_convert_case')) {
            $text = mb_convert_case($text, MB_CASE_LOWER, Mage_Core_Helper_String::ICONV_CHARSET);
        }
        return $text;
    }

    /**
     * Copy specific fields from one address to another
     *
     * @param Mage_Sales_Model_Quote_Address $from
     * @param  $to
     * @return Mage_Sales_Model_Quote_Address
     */
    public function copyAddress(Mage_Sales_Model_Quote_Address $from, Mage_Sales_Model_Quote_Address $to)
    {
        foreach ($this->_copyAddressFields as $field) {
            $to->setData($field, $from->getData($field));
        }
        return $to;
    }

    /**
     * Return default city
     *
     * @return string
     */
    public function getDefaultCity()
    {
        return Mage::getStoreConfig(self::XML_PATH_DEFAULT_CITY);
    }

    /**
     * Return default region
     *
     * @return string
     */
    public function getDefaultRegion()
    {
        return Mage::getStoreConfig(self::XML_PATH_DEFAULT_REGION);
    }

    /**
     * Return default country code
     *
     * @return string
     */
    public function getDefaultCountry()
    {
        return Mage::getStoreConfig(self::XML_PATH_DEFAULT_COUNTRY);
    }

    /**
     * Return choosen location type
     *
     * @return string
     */
    public function getLocationType()
    {
        return Mage::getStoreConfig(self::XML_PATH_LOCATION_FIELD_TYPE);
    }

    /**
     * Return choosen password type
     *
     * @return string
     */
    public function getPasswordType()
    {
        return Mage::getStoreConfig(self::XML_PATH_PASSWORD_FIELD_TYPE);
    }

    /**
     * Check possibility to show password field on checkout form
     *
     * @return bool
     */
    public function canShowPasswordField()
    {
        return $this->getPasswordType() == FI_Checkout_Model_Source::PASSWORD_FIELD;
    }

    /**
     * Checks possibility to use telephone field like password
     *
     * @return bool
     */
    public function isPasswordAsTelephone()
    {
        return $this->getPasswordType() == FI_Checkout_Model_Source::PASSWORD_PHONE;
    }

    /**
     * Checks possibility to automatically generate password
     *
     * @return bool
     */
    public function isPasswordAuto()
    {
        return $this->getPasswordType() == FI_Checkout_Model_Source::PASSWORD_GENERATE;
    }

    /**
     * Check is checkout only for one default country
     *
     * @return bool
     */
    public function useOnlyDefaultCountry()
    {
        return Mage::getStoreConfig(self::XML_PATH_USE_DEFAULT_COUNTRY);
    }

    /**
     * Check is country/region/city in one field
     *
     * @return bool
     */
    public function isLocationAsOneField()
    {
        return $this->getLocationType() == FI_Checkout_Model_Source::LOCATION_ONE;
    }

    /**
     * Return address information stored in session.
     * If session is empty, return default shipping information.
     *
     * @return array
     */
    public function getAddressInfo()
    {
        $session = Mage::getSingleton('customer/session');
        $data    = $session->getShippingInfo();

        return $data;
    }

    /**
     * Update Shipping Address based on session and default config values.
     *
     * @param  Varien_Object $address
     * @return Varien_Object
     */
    public function updateAddress(Varien_Object $address)
    {
        $data = $this->getAddressInfo();
        if (!($data || $address->getCountryId() || $address->getCity())) {
            $data = $this->getDefaultShippingAddress();
        }

        $shippingMethod = Mage::getSingleton('customer/session')->getShippingMethod();
        if ($shippingMethod) {
            $address->setShippingMethod($shippingMethod)
                    ->setCollectShippingRates(true);
        }

        if (is_array($data)) {
            $address->addData($data);
        }
        return $address;
    }

    /**
     * Auto assign shipping method if there is only one available method
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return FI_Checkout_Helper_Data
     */
    public function autoAssignShippingMethod(Mage_Sales_Model_Quote_Address $address)
    {
        $rates = $address->collectShippingRates()->getGroupedAllShippingRates();
        if (is_array($rates) && count($rates) == 1 && count(reset($rates)) == 1) {
            $rate = reset($rates);
            $rate = reset($rate);
            if (is_object($rate)) {
                $address->setShippingMethod($rate->getCode());
            }
        }
        $address->setCollectShippingRates(true);
        return $this;
    }

    /**
     * Check posibility to place order
     *
     * return bool
     */
    public function canPlaceOrder()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        return $quote->validateMinimumAmount();
    }

    /**
     * Generate random string
     *
     * @param int $len
     * return string
     */
    public function generateRandomKey($len = 20){
        $string = '';
        $pool   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($i = 1; $i <= $len; $i++)
          $string .= substr($pool, rand(0, 61), 1);

        return $string;
    }

    /**
     * Return password based on choosen type
     *
     * @param array $data user information
     * @return string
     */
    public function getPassword($data)
    {
        $password = '';
        switch ($this->getPasswordType()) {
            case FI_Checkout_Model_Source::PASSWORD_FIELD:
                if (!empty($data['password'])) {
                    $password = $data['password'];
                }
                break;
            case FI_Checkout_Model_Source::PASSWORD_PHONE:
                if (!empty($data['address']['telephone'])) {
                    $password = $data['address']['telephone'];
                    $plen = strlen($password);
                    if ($plen < 6) {
                        $password .= $this->generateRandomKey(6 - $plen);
                    }
                }
                break;
        }

        return $password ? $password : $this->generateRandomKey(8);
    }

    /**
     * Check is 3D secure validation required
     *
     * @param Mage_Payment_Model_Method_Abstract $paymentMethod
     * @return bool
     */
    public function isCentinelValidationRequired(Mage_Payment_Model_Method_Abstract $paymentMethod)
    {
        $result = false;
        if ($paymentMethod->getIsCentinelValidationEnabled()) {
            $centinel = $paymentMethod->getCentinelValidator();
            $result   = is_object($centinel);
        }
        return $result;
    }

    /**
     * Check is room and building fields enabled
     *
     * @return bool
     */
    public function showBuildRoomFields()
    {
        return Mage::getStoreConfig(self::XML_PATH_SHOW_BUILD_FIELDS);
    }

    /**
     * Check is guest checkout allowed
     * for future usage
     *
     * @return bool
     */
    public function isGuestCheckoutAllowed()
    {
        return Mage::getStoreConfig(self::XML_PATH_ALLOW_GUEST_CO);
    }

    /**
     * Return newsletter type
     *
     * @return string
     */
    public function getNewsletterType()
    {
        return Mage::getStoreConfig(self::XML_PATH_SHOW_NEWSLETTER);
    }

    /**
     * Check is newsletter visible on checkout page
     *
     * @return bool
     */
    public function isVisibleNewsletter()
    {
        return $this->getNewsletterType() != FI_Checkout_Model_Source::CHECKBOX_UNVISIBLE;
    }

    /**
     * Check is newsletter checkbox checked
     *
     * @return bool
     */
    public function isNewsletterChecked()
    {
        return $this->getNewsletterType() == FI_Checkout_Model_Source::CHECKBOX_CHECKED;
    }

    /**
     * Check is guest may subscribe to newsletter
     *
     * @return bool
     */
    public function maySubscribeGuest()
    {
        return Mage::getStoreConfig(self::XML_PATH_MAY_SUBSCRIBE_GUEST);
    }

    /**
     * Check is customer subscribed
     *
     * @return bool
     */
    public function isCustomerSubscribed()
    {
        $session = Mage::getSingleton('customer/session');
        if (!$this->isVisibleNewsletter()
            || !$session->isLoggedIn()
            && !$this->maySubscribeGuest()
        ) {
            return false;
        }

        $subscriber = Mage::getModel('newsletter/subscriber')->loadByCustomer($session->getCustomer());
        return $subscriber->isSubscribed();
    }

    /**
     * Check is need to send email to customer about subscription
     *
     * @return bool
     */
    public function isNeedSendNewsletterEmail($type)
    {
        switch ($type) {
            case 'request':
                return Mage::getStoreConfig(self::XML_PATH_NEWSLETTER_SEND_REQUEST_EMAIL);
            case 'success':
                return Mage::getStoreConfig(self::XML_PATH_NEWSLETTER_SEND_SUCCESS_EMAIL);
        }
        return true;
    }
}
