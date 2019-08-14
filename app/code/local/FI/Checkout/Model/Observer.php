<?php
/**
 * Zero Step Checkout observer model
 *
 * @category    FI
 * @package     FI_Checkout
 * @author      Sergiy Stotskiy <sergiy.stotskiy@freaksidea.com>
 */
class FI_Checkout_Model_Observer
{
    /**
     * Restore shipping method from session or auto assign if there is only one available method.
     * Listen event sales_quote_collect_totals_before
     *
     * @param Varien_Event_Observer $observer
     * @return FI_Checkout_Model_Observer
     */
    public function collectTotalsBefore(Varien_Event_Observer $observer)
    {
        $helper  = Mage::helper('fi_checkout');
        $quote   = $observer->getEvent()->getQuote();
        $address = $quote->getShippingAddress();

        $helper->updateAddress($address);
        $helper->autoAssignShippingMethod($address);
        return $this;
    }

    /**
     * Add user comment to order.
     * Listen event checkout_type_onepage_save_order
     *
     * @param Varien_Event_Observer $observer
     * @return FI_Checkout_Model_Observer
     */
    public function addOrderComment(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $request = Mage::app()->getRequest();

        $data = $request->getParam('user');
        if ($data && !empty($data['note'])) {
            $comment = strip_tags($data['note']);
            if (!empty($comment)) {
                $order->setCustomerNote($comment);
            }
        }
        return $this;
    }
}
