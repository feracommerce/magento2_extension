<?php
/**
 * @author: Sviatoslav Lashkiv
 * @email: ss.lashkiv@gmail.com
 * @team: MageCloud
 */

namespace Fera\Ai\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ResourceInterface as ModuleResourceInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Catalog\Block\Product\ImageBuilder;
use Magento\Catalog\Helper\Image;
use Fera\Ai\Logger\Logger;

/**
 * Class Data
 * @package Fera\Ai\Helper
 */
class Data extends AbstractHelper
{
    const FORMAT_DATE = 'Y-m-d\TH:i:sP';

    protected $moduleResource;
    protected $storeManager;
    protected $jsonHelper;
    protected $checkoutSession;
    protected $dateTime;
    protected $imageBuilder;
    protected $logger;

    public function __construct(
        Context $context,
        ModuleResourceInterface $moduleResource,
        StoreManagerInterface $storeManager,
        JsonHelper $jsonHelper,
        CheckoutSession $checkoutSession,
        DateTimeFactory $dateTime,
        ImageBuilder $imageBuilder,
        Logger $logger
    )
    {
        $this->moduleResource = $moduleResource;
        $this->storeManager = $storeManager;
        $this->jsonHelper = $jsonHelper;
        $this->checkoutSession = $checkoutSession;
        $this->dateTime = $dateTime;
        $this->imageBuilder = $imageBuilder;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Write to the Fera.ai log file
     * @param  mixed $msg message to log
     * @return $this
     */
    public function log($msg)
    {
        $this->logger->info($msg);
        return $this;
    }

    /**
     * Write to the debug output ONLY if the debug mode is enabled
     * @param  mixed $msg Message to log
     * @return $this
     */
    public function debug($msg)
    {
        if ($this->isDebugMode()) {
            return $this->log($msg);
        }

        return $this;
    }

    /**
     * @return String Version of the extension (x.x.x)
     */
    public function getVersion()
    {
        return $this->moduleResource->getDbVersion('Fera_Ai');
    }

    /**
     * Fera Ai public key either from the store config or the environment files
     * @return string
     */
    public function getPublicKey()
    {
        return $this->scopeConfig->getValue(
            'fera_ai/fera_ai_group/public_key',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Fera Ai secret (private) key, either from the environment fiels or the store config
     * @return string
     */
    public function getSecretKey()
    {
        return $this->scopeConfig->getValue(
            'fera_ai/fera_ai_group/secret_key',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function isEnabled()
    {
        if (!$this->isConfigured()) {
            return false;
        }

        return $this->scopeConfig->getValue(
            'fera_ai/general/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * True if the current Fera Ai configuration is setup to work properly
     * @return boolean false if it is not ready for use
     */
    public function isConfigured()
    {
        $publicKey = $this->getPublicKey();
        $secretKey = $this->getSecretKey();
        $apiUrl = $this->getApiUrl();
        $jsUrl = $this->getJsUrl();
        return !empty($publicKey) && !empty($secretKey) && !empty($apiUrl) && !empty($jsUrl);
    }

    /**
     * The URL path to the API (https). For example: https://api.fera.ai/api/v1
     * @return string
     */
    public function getApiUrl()
    {
        return $this->scopeConfig->getValue(
            'fera_ai/fera_ai_group/api_url',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * The URL to the javascript file on the Fera CDN. For example: https://cdn.fera.ai/js/bananastand.js
     * @return string
     */
    public function getJsUrl()
    {
        return $this->scopeConfig->getValue(
            'fera_ai/fera_ai_group/js_url',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is debug mode enabled? If so we will output much more extra info to the logs to help developers.
     * @return boolean
     */
    public function isDebugMode()
    {
        return $this->scopeConfig->isSetFlag(
            'fera_ai/general/debug_mode',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function serializeQuoteItems($items)
    {
        $configurableItems = [];
        $itemMap = [];
        $childItems = [];
        foreach ($items as $cartItem) {

            if ($cartItem->getParentItemId()) {
                $childItems[] = $cartItem;
            } else {
                $itemMap[$cartItem->getId()] = [
                    'product_id' => $cartItem->getProductId(),
                    'price' => $cartItem->getPrice(),
                    'total' => $cartItem->getRowTotal(),
                    'name' => $cartItem->getName()
                ];
                if ($cartItem->getProductType() == 'configurable') {
                    $configurableItems[$cartItem->getId()] = $itemMap[$cartItem->getId()];
                }
            }

        }

        foreach ($childItems as $cartItem) {
            if ($configurableItems[$cartItem->getParentItemId()]) {
                // product is configurable
                $itemMap[$cartItem->getParentItemId()]['name'] = $cartItem->getName();
                $itemMap[$cartItem->getParentItemId()]['variant_id'] = $cartItem->getProductId();
            } else {
                // product is bundle or something else, just add it as a normal item

                $itemMap[$cartItem->getId()] = [
                    'product_id' => $cartItem->getProductId(),
                    'price' => $cartItem->getPrice(),
                    'total' => $cartItem->getRowTotal(),
                    'name' => $cartItem->getName()
                ];
            }
        }

        return array_values($itemMap);
    }

    /**
     * @return string - The contents of the cart as a json string.
     */
    public function getCartJson()
    {
        $quote = $this->checkoutSession->getQuote();

        $data = [
            'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            'total' => $quote->getSubtotal(),
            'grand_total' => $quote->getGrandTotal()
        ];

        $data['items'] = $this->serializeQuoteItems($quote->getAllVisibleItems());

        return $this->jsonEncode($data);
    }

    /**
     * @return string - JS to trigger debug mode if required.
     */
    public function getDebugJs() {
        if ($this->isDebugMode()) {
            return "window.feraDebugMode = true;";
        }
        return "";
    }

    public function jsonEncode($data)
    {
        return $this->jsonHelper->jsonEncode($data);
    }

    public function formatDate($date)
    {
        return $this->dateTime->create($date)->format(self::FORMAT_DATE);
    }

    public function getImage($product, $imageId, $attributes = [])
    {
        return $this->imageBuilder->setProduct($product)
            ->setImageId($imageId)
            ->setAttributes($attributes)
            ->create();
    }

    public function getProductThumbnailUrl($product)
    {
        $imageType = 'product_thumbnail_image';
        $image = $this->getImage($product, $imageType);
        return $image->getImageUrl();
    }

}
