<?php
/**
 * @author: Sviatoslav Lashkiv
 * @email: ss.lashkiv@gmail.com
 * @team: MageCloud
 */

namespace Fera\Ai\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Fera\Ai\Helper\Data;

/**
 * Class Footer
 * @package Fera\Ai\Block
 */
class Footer extends Template
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * Footer constructor.
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        Data $helper,
        array $data = [])
    {
        $this->customerSession = $customerSession;
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    public function isEnabled()
    {
        return $this->helper->isEnabled();
    }

    public function getDebugJs()
    {
        return $this->helper->getDebugJs();
    }

    public function getPublicKey()
    {
        return $this->helper->getPublicKey();
    }

    public function getAppUrl()
    {
        return $this->helper->getAppUrl();
    }

    public function getApiUrl()
    {
        return $this->helper->getApiUrl();
    }

    public function getJsUrl()
    {
        return $this->helper->getJsUrl();
    }

    public function getShopperData()
    {
        $customer = $this->customerSession->getCustomer();

        if (!$customer->getId()) return false;

        $shopperData = [
            'customer_id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'name' => $customer->getName(),
        ];

        return $this->helper->jsonEncode($shopperData);
    }

    public function getCartJson()
    {
        return $this->helper->getCartJson();
    }

    public function jsonEncode($data)
    {
        return $this->helper->jsonEncode($data);
    }
}
