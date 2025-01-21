<?php
namespace Fera\Ai\Observer\Frontend;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\Order as Order;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Framework\HTTP\Client\Curl as Curl;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Directory\Model\Currency as Currency;
use Magento\Directory\Helper\Data as DirectoryHelperData;
use Magento\Customer\Model\Session as CustomerModelSession;

use Fera\Ai\Helper\Data as FeraHelper;

class OrderPushEvent implements ObserverInterface
{
    protected $_storeManager;
    protected $_currency;
    protected $_curl;
    protected $helper;
    protected $order;
    protected $customerSession;
    protected $_checkoutSession;
    protected $directoryHelper;

   /**
     * Sales order constructor.
     * @param FeraHelper $helper
     * @param Order $order
     * @param StoreManager $storeManager
     * @param Currency $currency
     * @param Config $orderConfig
     * @param DirectoryHelperData $directoryhelper
     * @param CustomerModelSession $customerSession
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        FeraHelper $helper,
        Order $order,
        Curl $curl,
        StoreManager $storeManager,
        Currency $currency,
        Config $orderConfig,
        DirectoryHelperData $directoryHelper,
        CustomerModelSession $customerSession,
        CheckoutSession $checkoutSession,
    )
    {
        $this->helper = $helper;
        $this->_curl = $curl;
        $this->order = $order;
        $this->directoryHelper = $directoryHelper;
        $this->customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_storeManager = $storeManager;
        $this->_currency = $currency;
    }

    /**
     * Send post request to push orders
     */
    public function pushOrders($data) {
        $url = $this->helper->getApiUrl()."v3/private/orders.json";
        $this->_curl->addHeader("Content-Type", "application/json");
        $this->_curl->addHeader("SECRET-KEY", $this->helper->getSecretKey());
        $this->_curl->post($url, $this->helper->jsonEncode($data));

        $response = $this->_curl->getBody();
    }

   /**
    * get orders data and and send request
    *
    * @param Observer $observer
    */
   public function execute(Observer $observer) {
      if (!$this->helper->isEnabled()) { return; }

      $orderId = $this->_checkoutSession->getLastOrderId();

      $order = $this->order->load($orderId);
      $customer = $this->getCustomerData($order);

      $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
      $total = $order->getGrandTotal();
      $totalUsd = $this->directoryHelper->currencyConvert($total, $currencyCode, 'USD');

      $orderData = [
                    'external_id'    => $orderId,
                    'number'        => $order->getIncrementId(),
                    'total'         => $total,
                    'total_usd'     => $totalUsd,
                    'external_created_at'    => $this->helper->formatDate($order->getCreatedAt()),
                    'external_updated_at'   => $this->helper->formatDate($order->getUpdatedAt()),
                    'line_items'    => $this->helper->serializeQuoteItems($order->getAllItems()),
                    'customer'      => $customer,
                    'external_customer_id' => $order->getCustomerId(),
                    'tags'          => [],
                    'source_name'   => 'web'
                  ];

      if (!empty($order->getShippingAddress())) { $orderData['shipping_address'] = $this->getShippingData($order); }
      if (!empty($order->getBillingAddress())) {
        $orderData['shipping_address'] = $this->getBillingData($order);
        $orderData['phone_number'] = $order->getBillingAddress()->getTelephone();
      }

    $this->pushOrders($orderData);

    return;
  }

  /**
   * get customer data
   */
  public function getCustomerData($order) {
      $customerId = $order->getCustomerId();
      $customerName = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();

      $customer = [
                    'external_id' => $customerId,
                    'name'    => $customerName,
                    'email' => $order->getCustomerEmail(),
                    'phone_number' => $order->getBillingAddress()->getTelephone(),
                  ];

    return $customer;
  }

  /**
   * get shhipping details
   */
  public function getShippingData($order) {
      $shippingAddress = $order->getShippingAddress();
      $shippingData = [
                        'name' => $shippingAddress->getData('firstname') . ' ' . $shippingAddress->getData('lastname'),
                        'address1' => $shippingAddress->getData('street'),
                        'city_name' => $shippingAddress->getData('city'),
                        'region_name' => $shippingAddress->getData('region'),
                        'zip_code' => $shippingAddress->getData('postcode'),
                      ];

      return $shippingData;
  }

  /**
   * get billing data
   */

  public function getBillingData($order) {
      $billingAddress = $order->getBillingAddress();
      $billingData = [
                        'name' => $billingAddress->getData('firstname') . ' ' . $billingAddress->getData('lastname'),
                        'address1' => $billingAddress->getData('street'),
                        'city_name' => $billingAddress->getData('city'),
                        'region_name' => $billingAddress->getData('region'),
                        'zip_code' => $billingAddress->getData('postcode'),
                      ];
    return $billingData;
  }
}
