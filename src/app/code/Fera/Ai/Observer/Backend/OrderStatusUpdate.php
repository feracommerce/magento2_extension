<?php
namespace Fera\Ai\Observer\Backend;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Client\Curl as Curl;

use Fera\Ai\Helper\Data as FeraHelper;

class OrderStatusUpdate implements ObserverInterface
{

    protected $_curl;
    protected $helper;

   /**
     * Sales order constructor.
     * @param FeraHelper $helper
     */
    public function __construct(
        FeraHelper $helper,
        Curl $curl,
    )
    {
        $this->helper = $helper;
        $this->_curl = $curl;
    }

    /**
     * Send put request to update order status
     */
    public function updateOrderStatus($data) {
        $url = $this->helper->getApiUrl()."v3/private/orders/".$data['external_id']."fulfill";
        $this->_curl->addHeader("Content-Type", "application/json");
        $this->_curl->addHeader("SECRET-KEY", $this->helper->getSecretKey());
        $this->_curl->setOption(CURLOPT_CUSTOMREQUEST, "PUT");
        $this->_curl->post($url, $this->helper->jsonEncode($data));
        $response = $this->_curl->getBody();
       // $this->helper->log("Order status is updated for order". $response);
        //echo $response;
    }

   /**
    * get orders data and and send request
    *
    * @param Observer $observer
    */
   public function execute(\Magento\Framework\Event\Observer $observer) {
      if (!$this->helper->isEnabled()) { return; }

      $shipment = $observer->getEvent()->getShipment();
      $orderId = $shipment->getOrder()->getId();
      $shipmentData = [
                      'fulfilled_at' => $this->helper->formatDate($shipment->getCreatedAt()),
                      'external_id' => $orderId,
                      ];
      $this->updateOrderStatus($shipmentData);

     // $this->helper->log("Order id is: ". $orderId);
    //  $this->helper->log("Order created at is: ". $shipment->getCreatedAt());
     return;
  }
}
