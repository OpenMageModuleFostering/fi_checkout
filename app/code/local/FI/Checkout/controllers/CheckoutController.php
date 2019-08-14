<?php
/**
 * Zero Step Checkout controller
 *
 * @category    FI
 * @package     FI_Checkout
 * @author      Sergiy Stotskiy <sergiy.stotskiy@freaksidea.com>
 */
class FI_Checkout_CheckoutController extends Mage_Checkout_Controller_Action
{
    /**
     * Pre dispatch hook. Remove addresses created by multishipping checkout
     *
     */
    public function preDispatch()
    {
        parent::preDispatch();

        $checkoutSessionQuote = Mage::getSingleton('checkout/session')->getQuote();
        if ($checkoutSessionQuote->getIsMultiShipping()) {
            $checkoutSessionQuote->setIsMultiShipping(false);
            $checkoutSessionQuote->removeAllAddresses();
        }

        return $this;
    }

    /* @var $_order Mage_Sales_Model_Order */
    protected $_order;

    /**
     * Get Order by quoteId
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getOrder()
    {
        if (is_null($this->_order)) {
            $this->_order = Mage::getModel('sales/order')->load($this->getOnepage()->getQuote()->getId(), 'quote_id');
            if (!$this->_order->getId()) {
                throw new Mage_Payment_Model_Info_Exception(Mage::helper('core')->__("Can not create invoice. Order was not found."));
            }
        }
        return $this->_order;
    }

    /**
     * Set specific headers if session expired and send response
     *
     * @return FI_Checkout_CheckoutController
     */
    protected function _ajaxRedirectResponse()
    {
        $this->getResponse()
            ->setHeader('HTTP/1.1', '403 Session Expired')
            ->setHeader('Login-Required', 'true')
            ->sendResponse();
        return $this;
    }

    /**
     * Check if session expired. If session expired call self::_ajaxRedirectResponse method
     *
     * @return bool
     */
    protected function _expireAjax()
    {
        if (!$this->getOnepage()->getQuote()->hasItems()
            || $this->getOnepage()->getQuote()->getHasError()
            || $this->getOnepage()->getQuote()->getIsMultiShipping()
        ) {
            $this->_ajaxRedirectResponse();
            return true;
        }

        return false;
    }

    /**
     * Get customer session
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Process error. If error exists throw exception
     *
     * @param mixed $result
     * @return FI_Checkout_CheckoutController
     */
    protected function _processError($result)
    {
        if (isset($result['error'])) {
            $message = $result['message'];
            if (is_array($message)) {
                $message = join('<br>', $message);
            }
            Mage::throwException($message);
        }
        return $this;
    }

    /**
     * Subscribe customer to newsletter
     *
     * @param array $data  customer information
     * @param bool  $isWantSubscribe
     * @return bool
     */
    protected function _subscribeCustomer($data, $isWantSubscribe)
    {
        $helper = Mage::helper('fi_checkout');
        if (!$isWantSubscribe
            || !$helper->isVisibleNewsletter()
            || $isWantSubscribe && $helper->isCustomerSubscribed()
        ) {
            return false;
        }

        $ownerId = Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail($data['email'])
            ->getId();

        $session = $this->_getSession();
        if ($ownerId !== null && $ownerId != $session->getCustomer()->getId()) {
            Mage::throwException(Mage::helper('newsletter')->__('Sorry, you are trying to subscribe email assigned to another user'));
        }

        $status = Mage::getModel('fi_checkout/subscriber')
            ->setIsSendSuccessEmail($helper->isNeedSendNewsletterEmail('success'))
            ->setIsSendRequestEmail($helper->isNeedSendNewsletterEmail('request'))
            ->subscribe($data['email']);
        return Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED == $status;
    }

    /**
     * Prepare address information from request
     *
     * @param array $data
     * @return array
     */
    protected function _prepareAddress($data)
    {
        $address = $data['address'];
        if (empty($address['id'])) {
            $address['id'] = false;
        }
        if (isset($data['name'])) {
            $name = explode(' ', trim($data['name']), 2);
            $name = array_filter($name, 'trim');
        } else {
            $customer = $this->_getSession()->getCustomer();
            $name = array(
                $customer->getFirstname(),
                $customer->getLastname()
            );
            $data['email']        = $customer->getEmail();
            if (empty($address['telephone']) && $customer->getPrimaryShippingAddress()) {
                $address['telephone'] = $customer->getPrimaryShippingAddress()->getTelephone();
            }
        }

        $address['firstname']  = trim($name[0]);
        $address['lastname']   = trim(isset($name[1]) ? $name[1] : '');
        $address['email']      = $data['email'];

        if (!$this->_getSession()->isLoggedIn()) {
            $password = Mage::helper('fi_checkout')->getPassword($data);
            $address['customer_password'] = $password;
            $address['confirm_password']  = $password;
        }
        $address['use_for_shipping']  = true;

        $request = new Varien_Object($address);
        if (!empty($address['location'])) {
            $request->setData('value', $address['location']);
        }
        $locData = $this->_parseAddress($request);
        $address['country_id'] = $locData['country_id'];
        $address['region_id']  = $locData['region_id'];
        $address['region']     = $locData['region'];
        $address['city']       = $locData['city'];
        $address['postcode']   = $locData['postcode'];

        return $address;
    }

    /**
     * Create invoice
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function _initInvoice()
    {
        $items = array();
        foreach ($this->_getOrder()->getAllItems() as $item) {
            $items[$item->getId()] = $item->getQtyOrdered();
        }
        /* @var $invoice Mage_Sales_Model_Service_Order */
        $invoice = Mage::getModel('sales/service_order', $this->_getOrder())->prepareInvoice($items);
        $invoice->setEmailSent(true)->register();

        return $invoice;
    }

    /**
     * Place order. Check for billing agreements and zero subtotal
     */
    protected function _placeOrder()
    {
        if ($requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds()) {
            $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
            if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                $result['success'] = false;
                $result['error'] = true;
                $result['message'] = Mage::helper('checkout')->__('Please agree to all the terms and conditions before placing the order.');
                $this->_processError($result);
                return;
            }
        }

        // update payment information from request for order payment
        if ($payment = $this->getRequest()->getPost('payment')) {
            $this->getOnepage()
                ->getQuote()
                ->getPayment()
                ->importData($payment);
        }

        $this->getOnepage()->saveOrder();

        $storeId = Mage::app()->getStore()->getId();
        $paymentHelper = Mage::helper("payment");
        $zeroSubTotalPaymentAction = $paymentHelper->getZeroSubTotalPaymentAutomaticInvoice($storeId);
        if ($paymentHelper->isZeroSubTotal($storeId)
                && $this->_getOrder()->getGrandTotal() == 0
                && $zeroSubTotalPaymentAction == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE
                && $paymentHelper->getZeroSubTotalOrderStatus($storeId) == 'pending'
        ) {
            $invoice = $this->_initInvoice();
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->save();
        }
    }

    /**
     * Return easy checkout page model
     *
     * @return FI_Checkout_Model_Page
     */
    public function getOnepage()
    {
        return Mage::getSingleton('fi_checkout/page');
    }

    /**
     * Customer login action.
     * Set before_auth_url to session and forwards to Mage_Customer_AccountController::loginPost
     */
    public function loginAction()
    {
        $this->_getSession()->setBeforeAuthUrl(Mage::getUrl('checkout/cart'))
            ->setCheckoutUser(array())
            ->unsShippingInfo()
            ->unsShippingMethod();
        $this->_forward('loginPost', 'account', 'customer');
    }

    /**
     * Country/region/city autocompleter.
     * Depends on request param "location". Set html to response.
     *
     * @return Mage_Core_Controller_Response_Http
     */
    public function locationAutocompleteAction()
    {
        $location = $this->getRequest()->getParam('location');
        if (!$location) {
            return;
        }

        $helper = Mage::helper('fi_checkout');
        if ($helper->useOnlyDefaultCountry()) {
            $location = $helper->getDefaultCountry() . ',' . $location;
        }

        $items = Mage::getResourceModel('fi_checkout/countries')->filter($location);

        return $this->getResponse()->setBody($this->getLayout()
            ->createBlock('core/template')
            ->setTemplate('freaks/autocomplete.phtml')
            ->setItems($items)
            ->toHtml()
        );
    }

    /**
     * Checkout UI updater.
     * Update shipping/payment methods and totals blocks.
     * Depends on POST request with fields "type" and "value". "type" specify what parts of UI should be updated.
     *
     * @return Mage_Core_Controller_Response_Http
     */
    public function updateAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()
            || !$request->getParam('type')
            || !$request->getParam('value')
            || $this->_expireAjax()
        ) {
            return $this;
        }

        $blocks = array();

        $type = explode(',', $request->getParam('type'));
        $type = array_flip($type);

        if (isset($type['shipping'])) {
            $data = $this->_parseAddress(new Varien_Object($request->getParams()));
            if ($data) {
                $this->_getSession()->setShippingInfo($data);
            }
        }
        $this->_syncAddress($this->_getSession()->getShippingInfo());

        $shippingMethod = $request->getPost('shipping_method', $this->_getSession()->getShippingMethod());
        if ($shippingMethod) {
            $this->_getSession()->setShippingMethod($shippingMethod);
        }

        $this->_syncAddress(array('shipping_method' => $shippingMethod));

        $this->getLayout()->getUpdate()
            ->load('fi_checkout_update');
        $this->getLayout()->generateXml()
            ->generateBlocks();

        if (isset($type['shipping'])) {
            $blocks['shipping'] = $this->getLayout()
                ->getBlock('fi_checkout.shipping_method.available')
                ->toHtml();
        }

        if (isset($type['payment'])) {
            $blocks['payment'] = $this->getLayout()
                ->getBlock('fi_checkout.payment_methods')
                ->toHtml();
        }

        if (isset($type['totals'])) {
            $this->getOnepage()->getQuote()->collectTotals();
            $blocks['totals'] = $this->getLayout()
                ->getBlock('checkout.cart.totals')
                ->toHtml();
        }

        if ($blocks) {
            $response = new Varien_Object($blocks);
            return $this->getResponse()->setBody($response->toJson());
        }
    }

    /**
     * Set html to response. Return regions html select box for specific country.
     * Depends on "country_id" parameter
     */
    public function regionsAction()
    {
        $countryId = $this->getRequest()->getParam('country_id');
        if (!$countryId) {
            return $this;
        }

        $regions = Mage::getModel('directory/region')->getResourceCollection()
            ->addCountryFilter($countryId)
            ->load()
            ->toOptionArray();

        $html = $this->getLayout()->createBlock('core/html_select')
            ->setTitle(Mage::helper('checkout')->__('State/Province'))
            ->setClass('required-entry validate-state')
            ->setName('user[address][region_id]')
            ->setId('address-region')
            ->setOptions($regions)
            ->getHtml();

        $this->getResponse()->setBody($html);
    }

    /**
     * Update shipping/billing address information
     *
     * @param array $data
     * @return FI_Checkout_CheckoutController
     */
    protected function _syncAddress($data)
    {
        if (!is_array($data)) {
            return $this;
        }

        $address = $this->getOnepage()->getQuote()
            ->getShippingAddress();

        if (!empty($data['shipping_method'])) {
            $address->setShippingMethod($data['shipping_method']);
            unset($data['shipping_method']);
        }

        if ($data) {
            $address->addData($data);

            $this->getOnepage()->getQuote()
                ->getBillingAddress()
                ->addData($data);
        }

        return $this;
    }

    /**
     * Parse address information from request
     *
     * @param Varien_Object $request
     * @return array
     */
    protected function _parseAddress($request)
    {
        $helper = Mage::helper('fi_checkout');
        $data   = array(
            'country_id' => null,
            'region'     => null,
            'region_id'  => null,
            'city'       => null
        );

        if ($helper->isLocationAsOneField()) {
            $loc = $request->getData('value');
            if ($helper->useOnlyDefaultCountry()) {
                $loc = $helper->getDefaultCountry() . ',' . $loc;
            }
            $loc = explode(',', $loc);
            $loc = array_map('trim', $loc);

            if (!empty($loc[0])) {
                $loc[0]    = $helper->lowerCase($loc[0]);
                $countries = array_flip(array_map(
                    array($helper, 'lowerCase'),
                    Mage::app()->getLocale()->getCountryTranslationList()
                ));

                if (!$helper->useOnlyDefaultCountry() && isset($countries[$loc[0]])) {
                    $data['country_id'] = $countries[$loc[0]];
                } else {
                    $data['country_id'] = $helper->getDefaultCountry();
                }

                if (!empty($loc[1])) {
                    $data['city'] = $data['region'] = $loc[1];
                }

                if (!empty($loc[2])) {
                    $data['city'] = $loc[2];
                }
            }
        } else {
            $data['country_id'] = $request->getData('country_id');
            if ($helper->useOnlyDefaultCountry() || empty($data['country_id'])) {
                $data['country_id'] = $helper->getDefaultCountry();
            }

            $data['region'] = $request->getData('region');
            $data['city']   = $request->getData('city');
        }

        $data['postcode'] = $request->getData('postcode');
        if (!empty($data['region'])) {
            $data['region_id'] = Mage::getResourceModel('fi_checkout/countries')
                ->getRegionIdByName(trim($data['region']), $data['country_id']);
            if ($data['region_id']) {
                $data['region'] = null;
            }
        }

        return $data;
    }

    /**
     * Set response in special format
     *
     * @param Varien_Object $response
     * @return FI_Checkout_CheckoutController
     */
    protected function _response(Varien_Object $response)
    {
        if ($response->hasErrorMessage()) {
            $errorHtml = $this->getLayout()
                ->createBlock('core/messages')
                ->addError($response->getErrorMessage())
                ->getGroupedHtml();

            $response->setErrorMessage($errorHtml);
        }

        $this->getResponse()->setBody($response->toJson());

        return $this;
    }

    /**
     * Place order action. Listen for POST requests only
     *
     * @return FI_Checkout_CheckoutController
     */
    public function placeAction()
    {
        $response = new Varien_Object();
        $data = $this->getRequest()->getPost('user');
        if (!$this->getRequest()->isPost() || !$data || $this->_expireAjax()) {
            $response->setRedirect(Mage::getUrl('checkout/cart'));
            return $this->_response($response);
        }

        $session  = $this->_getSession();
        $hasError = false;
        $quote    = $this->getOnepage()->getQuote();
        $session->setCheckoutUser($data);

        if (!$quote->validateMinimumAmount()) {
            $error = Mage::getStoreConfig('sales/minimum_order/error_message');
            $response->setErrorMessage($error);
            return $this->_response($response);
        }

        try {
            // save checkout method
            if ($session->isLoggedIn()) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER);
            } elseif (empty($data['address']['id'])) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            }
            $this->getOnepage()->initCheckout();

            // save addresses
            $data['address'] = $this->_prepareAddress($data);
            $result  = $this->getOnepage()->saveBilling($data['address'], $data['address']['id']);
            $this->_processError($result);

            $this->_subscribeCustomer($data['address'], $this->getRequest()->getPost('subscribe', false));

            // save shipping method
            $shippingMethod = $this->getRequest()->getPost('shipping_method');
            if (!$quote->getIsVirtual() && empty($shippingMethod)) {
                $this->_processError(array(
                    'error'   => -1,
                    'message' => Mage::helper('checkout')->__('Invalid shipping method.')
                ));
            }

            /**
             * Addresses has been validated in saveBilling method,
             * so we are disabled validation
             */
            $quote->getShippingAddress()
                ->setShippingMethod($shippingMethod)
                ->setSaveInAddressBook(!$session->getCustomer() || !$session->getCustomer()->getDefaultShipping())
                ->setShouldIgnoreValidation(true);

            $quote->getBillingAddress()
                ->setSaveInAddressBook(!$session->getCustomer() || !$session->getCustomer()->getDefaultBilling())
                ->setShouldIgnoreValidation(true);

            Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method', array(
                'request' => $this->getRequest(),
                'quote'   => $quote
            ));

            $quote->setTotalsCollectedFlag(false)
                ->collectTotals();

            // save payment information
            $payment = $this->getRequest()->getPost('payment');
            $this->getOnepage()->savePayment($payment);

            $redirectUrl = $quote->getPayment()->getCheckoutRedirectUrl();
            if ($redirectUrl) {
                $response->setRedirect($redirectUrl);
                return $this->_response($response);
            }

            // save order
            $this->_placeOrder();
            $redirectUrl = $this->getOnepage()
                ->getCheckout()
                ->getRedirectUrl();

            $quote->setIsActive(!empty($redirectUrl))->save();

            $session->setCheckoutUser(array())
                ->unsShippingInfo();
        } catch (Mage_Core_Exception $e) {
            $hasError = true;
            $response->setErrorMessage($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
            $hasError = true;
            $response->setErrorMessage(Mage::helper('checkout')->__('Unable to process your order. Please try again later'));
        }

        if (!$redirectUrl && !$hasError) {
            $redirectUrl = Mage::getUrl('*/*/success');
        }

        $response->setError($hasError)
            ->setSuccess(!$hasError)
            ->setRedirect($redirectUrl);
        $this->_response($response);

        Mage::dispatchEvent('controller_action_postdispatch_checkout_onepage_saveOrder', array(
            'controller_action' => $this
        ));

        return $this;
    }

    /**
     * Order success action
     */
    public function successAction()
    {
        $session = $this->getOnepage()->getCheckout();
        if (!$session->getLastSuccessQuoteId()) {
            $this->_redirect('checkout/cart');
            return;
        }

        $lastQuoteId = $session->getLastQuoteId();
        $lastOrderId = $session->getLastOrderId();
        $lastRecurringProfiles = $session->getLastRecurringProfileIds();
        if (!$lastQuoteId || (!$lastOrderId && empty($lastRecurringProfiles))) {
            $this->_redirect('checkout/cart');
            return;
        }

        $session->clear();
        $this->loadLayout();
        $this->_initLayoutMessages('checkout/session');
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($lastOrderId)));
        $this->renderLayout();
    }

    /**
     * Verify card using 3D secure
     *
     * @return FI_Checkout_CheckoutController
     */
    public function verifyAction()
    {
        $payment = $this->getRequest()->getPost('payment');
        if (!$payment) {
            return $this;
        }

        $verifyUrl = '';
        $quote = $this->getOnepage()->getQuote();
        $this->getOnepage()->savePayment($payment);

        $paymentMethod = $quote->getPayment()->getMethodInstance();
        if ($paymentMethod && $paymentMethod->getIsCentinelValidationEnabled()) {
            $centinel = $paymentMethod->getCentinelValidator();
            if ($centinel && $centinel->shouldAuthenticate()) {
                $verifyUrl = $centinel->getAuthenticationStartUrl();
            }
        }

        if ($verifyUrl) {
            $html = $this->getLayout()->createBlock('core/template')
                ->setTemplate('freaks/checkout/centinel/authentication.phtml')
                ->setFrameUrl($verifyUrl)
                ->toHtml();
        } else {
            $html = $this->getLayout()->createBlock('core/template')
                ->setTemplate('freaks/checkout/centinel/complete.phtml')
                ->setIsProcessed(true)
                ->setIsSuccess(true)
                ->toHtml();
        }

        $response  = new Varien_Object(array(
            'url'  => $verifyUrl,
            'html' => $html
        ));

        $this->getResponse()->setBody($response->toJson());
        return $this;
    }
}
