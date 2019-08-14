<?php
/**
 * Zero Step Checkout source model
 *
 * @category    FI
 * @package     FI_Checkout
 * @author      Sergiy Stotskiy <sergiy.stotskiy@freaksidea.com>
 */
class FI_Checkout_Model_Source
{
    /**
     * Constants for password types
     */
    const PASSWORD_FIELD    = 'field';
    const PASSWORD_GENERATE = 'generate';
    const PASSWORD_PHONE    = 'phone';

    /**
     * Constants for location types
     */
    const LOCATION_ONE = 'one';
    const LOCATION_FEW = 'few';

    /**
     * Constants for checkbox types
     */
    const CHECKBOX_UNVISIBLE = 'unvisible';
    const CHECKBOX_UNCHECKED = 'unchecked';
    const CHECKBOX_CHECKED   = 'checked';

    /**
     * Return a list of password types
     *
     * @return array
     */
    public function getPasswordTypes()
    {
        return array(
            self::PASSWORD_FIELD     => Mage::helper('fi_checkout')->__('Password Field on Checkout'),
            self::PASSWORD_PHONE     => Mage::helper('fi_checkout')->__('Password as Telephone Field'),
            self::PASSWORD_GENERATE  => Mage::helper('fi_checkout')->__('Auto Generate Password')
        );
    }

    /**
     * Return a list of location types
     *
     * @return array
     */
    public function getLocationTypes()
    {
        return array(
            self::LOCATION_ONE => Mage::helper('fi_checkout')->__('Country, Region, City as One Field'),
            self::LOCATION_FEW => Mage::helper('fi_checkout')->__('Country, Region, City as Different Fields')
        );
    }

    /**
     * Return a list of checkbox types
     *
     * @return array
     */
    public function getCheckboxTypes()
    {
        return array(
            self::CHECKBOX_UNVISIBLE => Mage::helper('fi_checkout')->__('Not Visible'),
            self::CHECKBOX_UNCHECKED => Mage::helper('fi_checkout')->__('Visible, Unchecked'),
            self::CHECKBOX_CHECKED   => Mage::helper('fi_checkout')->__('Visible, Checked')
        );
    }
}
