<?php
/**
 * Zero Step Checkout Counrty/region helper model
 *
 * @category    FI
 * @package     FI_Checkout
 * @author      Sergiy Stotskiy <sergiy.stotskiy@freaksidea.com>
 */
class FI_Checkout_Model_Resource_Countries extends Mage_Directory_Model_Mysql4_Country_Collection
{
    protected
        /**
         * Table name of region locale names
         *
         * @var string
         */
        $_regionNameTable,

        /**
         * Table name of region
         *
         * @var string
         */
        $_regionTable;

    /**
     * Constructor. Assign table names to properties
     */
    public function __construct()
    {
        parent::__construct();

        $r = Mage::getSingleton('core/resource');
        $this->_countryTable    = $r->getTableName('directory/country');
        $this->_regionTable     = $r->getTableName('directory/country_region');
        $this->_regionNameTable = $r->getTableName('directory/country_region_name');

        $this->setItemObjectClass('Varien_Object');
    }

    /**
     * Add region names to collection
     *
     * @return FI_Checkout_Model_Resource_Countries
     */
    public function addRegionNames()
    {
        $this->getSelect()
            ->joinLeft(
                array('r' => $this->_regionTable),
                'country.country_id = r.country_id',
                array('default_name')
            )
            ->joinInner(
                array('rn' => $this->_regionNameTable),
                $this->getSelect()->getAdapter()->quote('rn.region_id = r.region_id AND locale = ?', Mage::app()->getLocale()->getLocaleCode()),
                array('name')
            );

        return $this;
    }

    /**
     * Filter collection of country/regions by specific value.
     * Uses for autocomplete.
     *
     * @param string $location
     * @param int $limit
     * @return array
     */
    public function filter($location, $limit = 15)
    {
        $result    = array();
        $helper    = Mage::helper('core/string');
        $location  = explode(',', $location);
        $location  = array_map('trim', $location);
        $countries = Mage::app()->getLocale()
            ->getCountryTranslationList();

        if (empty($location[0])) {
            return $result;
        }

        $checkoutHelper = Mage::helper('fi_checkout');
        if (empty($location[1])) {
            $i   = 0;
            $exp = $checkoutHelper->lowerCase($location[0]);
            foreach ($countries as $code => $name) {
                $title = $checkoutHelper->lowerCase($name);
                if ($helper->strpos($title, $exp) !== false) {
                    $result[] = $name;
                    $i++;
                    if ($i == $limit) {
                        break;
                    }
                }
            }
        }

        if (empty($result) && empty($location[2])) {
            if (empty($location[1])) {
                $location[1] = $location[0];
            }
            $location[0] = $checkoutHelper->lowerCase($location[0]);
            $countries   = array_flip(array_map(array($checkoutHelper, 'lowerCase'), $countries));
            if (isset($countries[$location[0]])) {
                $countryCode = $countries[$location[0]];
            } else {
                $countryCode = $checkoutHelper->getDefaultCountry();
            }

            $adapter = $this->getSelect()->getAdapter();
            $select  = $adapter->select()
                ->from(array('r' => $this->_regionTable), 'default_name')
                ->joinLeft(
                    array('rn' => $this->_regionNameTable),
                    $adapter->quote('rn.region_id = r.region_id AND locale = ?', Mage::app()->getLocale()->getLocaleCode()),
                    array('name')
                )
                ->where('r.country_id = ?', $countryCode)
                ->where('IF(name, name, default_name) LIKE ?', '%' . $location[1] . '%')
                ->limit($limit);

            $result = $adapter->fetchAll($select);
            foreach ($result as &$row) {
                $row = $row['name'] ? $row['name'] : $row['default_name'];
            }
        }

        if (empty($result) && empty($location[3])) {
            if (empty($location[2])) {
               $location[2] = $location[1];
            }
            // city autocomplete
        }

        return $result;
    }

    /**
     * Get region id by name
     *
     * @param string $name
     * @param string $countryId
     * @return int|null
     */
    public function getRegionIdByName($name, $countryId = null)
    {
        $adapter = $this->getSelect()->getAdapter();
        $select  = $adapter->select()
            ->from(array('r' => $this->_regionTable), array('default_name', 'region_id'))
            ->joinLeft(
                array('rn' => $this->_regionNameTable),
                $adapter->quote('rn.region_id = r.region_id AND locale = ?', Mage::app()->getLocale()->getLocaleCode()),
                array('name')
            )
            ->where('name = ? OR default_name = ?', $name, $name);

        if ($countryId) {
            $select->where('r.country_id = ?', $countryId);
        }

        $row = $adapter->fetchRow($select);
        return empty($row['region_id']) ? null : $row['region_id'];
    }
}
