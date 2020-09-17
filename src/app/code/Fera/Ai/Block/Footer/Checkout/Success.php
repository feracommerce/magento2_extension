<?php
/**
 * @author: Sviatoslav Lashkiv
 * @email: ss.lashkiv@gmail.com
 * @team: MageCloud
 */

namespace Fera\Ai\Block\Footer\Checkout;

use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order\Config;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Helper\Data as DirectoryHelperData;
use Fera\Ai\Helper\Data as FeraHelper;

/**
 * Class Success
 * @package Fera\Ai\Block\Footer\Checkout
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var DirectoryHelperData
     */
    protected $directoryHelper;

    /**
     * @var FeraHelper
     */
    protected $helper;

    /**
     * Success constructor.
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param Config $orderConfig
     * @param HttpContext $httpContext
     * @param CustomerSession $customerSession
     * @param DirectoryHelperData $directoryHelper
     * @param FeraHelper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        Config $orderConfig,
        HttpContext $httpContext,
        CustomerSession $customerSession,
        DirectoryHelperData $directoryHelper,
        FeraHelper $helper,
        array $data = []
    )
    {
        $this->customerSession = $customerSession;
        $this->directoryHelper = $directoryHelper;
        $this->helper = $helper;
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
    }

    /**
     * @return bool|mixed
     */
    public function isEnabled()
    {
        return $this->helper->isEnabled();
    }

    /**
     * Get last order, JSONify it and return it.
     * @return String
     */
    public function getOrderJson()
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        $customer = null;
        if (!empty($this->customerSession->getCustomerId())) {
            $customer = $this->customerSession->getCustomer();
            $address = $customer->getDefaultShippingAddress();

            if (!empty($address)) {
                $address = $address->getData();
            }

            $customer = [
                'id'            => $this->customerSession->getCustomerId(),
                'first_name'    => $this->customerSession->getCustomer()->getFirstname(),
                'email'         => $this->customerSession->getCustomer()->getEmail(),
                'address'       => $address
            ];
        }

        $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $total = $order->getGrandTotal();
        $totalUsd = $this->directoryHelper->currencyConvert($total, $currencyCode, 'USD');

        $orderData = [
            'id'            => $order->getId(),
            'number'        => $order->getIncrementId(),
            'total'         => $total,
            'total_usd'     => $totalUsd,
            'created_at'    => $this->helper->formatDate($order->getCreatedAt()),
            'modified_at'   => $this->helper->formatDate($order->getUpdatedAt()),
            'line_items'    => $this->helper->serializeQuoteItems($order->getAllItems()),
            'customer'      => $customer,
            'source_name'   => 'web'
        ];

        return $this->helper->jsonEncode($orderData);
    }

}
